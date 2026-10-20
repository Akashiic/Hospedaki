<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\BlockedDate;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Diz quais diárias de uma acomodação estão livres.
 * Uma diária é a noite que começa na data indicada.
 *
 * Reservas por hora (day use, eventos) ocupam só a faixa de horário na acomodação reservada.
 * Para as outras do mesmo imóvel (shared_unit), que são reservadas por diária, ocupam a noite
 * daquele dia: um evento no salão não convive com hóspedes no apartamento que fica dentro dele.
 */
class AvailabilityService
{
    public function isAvailable(Accommodation $accommodation, CarbonInterface $checkIn, CarbonInterface $checkOut, ?Booking $ignore = null): bool
    {
        return $this->unavailableNights($accommodation, $checkIn, $checkOut, $ignore) === [];
    }

    /**
     * Datas (Y-m-d) das noites ocupadas ou bloqueadas entre $from (inclusive) e $to (exclusive).
     *
     * @return list<string>
     */
    public function unavailableNights(Accommodation $accommodation, CarbonInterface $from, CarbonInterface $to, ?Booking $ignore = null, bool $hourlyAsDays = true): array
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();

        $taken = [];
        // Reservas e bloqueios das acomodações que dividem o mesmo imóvel também ocupam esta.
        $unit = $accommodation->unitIds();

        $bookings = Booking::query()
            ->whereHas('accommodations', fn ($query) => $query->whereKey($unit))
            ->holdingDates()
            ->whereNull('start_time')
            ->whereDate('check_in', '<', $to)
            ->whereDate('check_out', '>', $from)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->get(['check_in', 'check_out']);

        foreach ($bookings as $booking) {
            $this->collect($taken, $booking->check_in, $booking->check_out->subDay(), $from, $to);
        }

        if ($hourlyAsDays) {
            foreach ($this->hourlyBookings($unit, $from, $to, $ignore) as $booking) {
                $taken[$booking->check_in->toDateString()] = true;
            }
        }

        $blocks = BlockedDate::query()
            ->whereIn('accommodation_id', $unit)
            ->whereDate('starts_on', '<', $to)
            ->whereDate('ends_on', '>=', $from)
            ->get();

        foreach ($blocks as $block) {
            $this->collect($taken, $block->starts_on, $block->ends_on, $from, $to);
        }

        ksort($taken);

        return array_keys($taken);
    }

    /**
     * Faixas já reservadas numa data, por hora, nesta acomodação e nas do mesmo imóvel.
     *
     * @return list<array{start: string, end: string}>
     */
    public function takenHours(Accommodation $accommodation, CarbonInterface $date, ?Booking $ignore = null): array
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        return $this->hourlyBookings($accommodation->unitIds(), $date, $date->addDay(), $ignore)
            ->map(fn (Booking $booking) => ['start' => Booking::hm($booking->start_time), 'end' => Booking::hm($booking->end_time)])
            ->sortBy('start')->values()->all();
    }

    /**
     * O dia inteiro está fechado para reserva por hora: data bloqueada ou estadia no mesmo imóvel.
     */
    public function dayClosedForHours(Accommodation $accommodation, CarbonInterface $date, ?Booking $ignore = null): bool
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        return $this->unavailableNights($accommodation, $date, $date->addDay(), $ignore, hourlyAsDays: false) !== [];
    }

    /**
     * A faixa [início, fim) está livre: o dia não está fechado e não cruza outra reserva por hora.
     */
    public function isHourAvailable(Accommodation $accommodation, CarbonInterface $date, string $start, string $end, ?Booking $ignore = null): bool
    {
        if ($this->dayClosedForHours($accommodation, $date, $ignore)) {
            return false;
        }

        [$from, $to] = [Booking::minutes($start), Booking::minutes($end)];

        foreach ($this->takenHours($accommodation, $date, $ignore) as $range) {
            if ($from < Booking::minutes($range['end']) && $to > Booking::minutes($range['start'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Dias de uma acomodação por hora em que não cabe mais nenhuma reserva: fechados, ou sem
     * uma janela livre do tamanho do mínimo de horas. Para o calendário do site.
     *
     * @return list<string>
     */
    public function unavailableHourDays(Accommodation $accommodation, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();
        $days = array_flip($this->unavailableNights($accommodation, $from, $to, hourlyAsDays: false));

        [$opens, $closes] = [Booking::minutes($accommodation->opensAt()), Booking::minutes($accommodation->closesAt())];
        $need = max(1, $accommodation->min_hours) * 60;

        $byDay = $this->hourlyBookings($accommodation->unitIds(), $from, $to)->groupBy(fn (Booking $b) => $b->check_in->toDateString());
        foreach ($byDay as $day => $bookings) {
            $cursor = $opens;
            $largest = 0;
            foreach ($bookings->sortBy(fn (Booking $b) => Booking::minutes($b->start_time)) as $booking) {
                $largest = max($largest, Booking::minutes($booking->start_time) - $cursor);
                $cursor = max($cursor, Booking::minutes($booking->end_time));
            }
            if (max($largest, $closes - $cursor) < $need) {
                $days[$day] = true;
            }
        }

        ksort($days);

        return array_keys($days);
    }

    /**
     * @param  list<int>  $unit
     * @return Collection<int, Booking>
     */
    private function hourlyBookings(array $unit, CarbonImmutable $from, CarbonImmutable $to, ?Booking $ignore = null)
    {
        return Booking::query()
            ->whereHas('accommodations', fn ($query) => $query->whereKey($unit))
            ->holdingDates()
            ->whereNotNull('start_time')
            ->whereDate('check_in', '>=', $from)
            ->whereDate('check_in', '<', $to)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->get(['id', 'check_in', 'start_time', 'end_time']);
    }

    /**
     * @param  array<string, true>  $taken
     */
    private function collect(array &$taken, CarbonInterface $start, CarbonInterface $end, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $start = CarbonImmutable::parse($start)->max($from);
        $end = CarbonImmutable::parse($end)->min($to->subDay());

        if ($start->gt($end)) {
            return;
        }

        foreach (CarbonPeriod::create($start, $end) as $night) {
            $taken[$night->toDateString()] = true;
        }
    }
}
