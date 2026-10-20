<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Números do painel (aba Estatísticas), sempre no fuso da pousada.
 *
 * Receita e ocupação contam por noite vivida: uma estadia de 3 noites que começa no fim do mês
 * entra parte num mês, parte no outro, e cada noite vale o subtotal dividido pelas noites.
 * Reservas por hora entram no dia do evento.
 *
 * Ocupação = noites vendidas ÷ noites disponíveis, por imóvel: acomodações que dividem o mesmo
 * imóvel (o Espaço e o apartamento dentro dele) contam como um só, porque uma ocupa a outra.
 */
class StatsService
{
    public const PERIODS = ['30d', 'month', '90d', '12m'];

    /**
     * @return array<string, mixed>
     */
    public function summary(Property $property, string $period): array
    {
        $today = $property->today();
        [$from, $to] = $this->range($period, $today);
        $length = (int) $from->diffInDays($to);
        [$prevFrom, $prevTo] = [$from->subDays($length), $from];

        $accommodations = $property->accommodations()->get();
        $nightly = $accommodations->reject(fn (Accommodation $a) => $a->isHourly());
        $bookings = $property->bookings()->with('accommodations')->get();
        $confirmed = $bookings->where('status', BookingStatus::Confirmed);

        $nights = $this->soldNights($confirmed, $nightly);
        $units = $this->unitCount($nightly);

        $current = $this->kpis($nights, $confirmed, $bookings, $from, $to, $units, $today);
        $previous = $this->kpis($nights, $confirmed, $bookings, $prevFrom, $prevTo, $units, $today);

        return [
            'period' => ['key' => $period, 'from' => $from->toDateString(), 'to' => $to->subDay()->toDateString(), 'days' => $length],
            'kpis' => $current,
            'previous' => array_intersect_key($previous, array_flip(['revenue', 'occupancy', 'adr', 'bookings'])),
            // Só compara quando o sistema já tinha reservas no começo do período anterior.
            'has_previous' => $bookings->isNotEmpty() && $bookings->min('check_in')->lte($prevFrom),
            'monthly' => $this->monthly($nights, $confirmed, $today, $units),
            'by_accommodation' => $this->byAccommodation($nights, $confirmed, $accommodations, $from, $to),
            'weekdays' => $this->weekdays($confirmed, $from, $to),
            'arrivals' => $this->arrivals($confirmed, $from, $to),
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} início (inclusive) e fim (exclusive) */
    public function range(string $period, CarbonImmutable $today): array
    {
        return match ($period) {
            'month' => [$today->startOfMonth(), $today->startOfMonth()->addMonth()],
            '90d' => [$today->subDays(89), $today->addDay()],
            '12m' => [$today->startOfMonth()->subMonths(11), $today->startOfMonth()->addMonth()],
            default => [$today->subDays(29), $today->addDay()],
        };
    }

    /**
     * Cada noite vendida de estadia: data, acomodação, imóvel e quanto ela vale.
     *
     * @param  Collection<int, Booking>  $confirmed
     * @param  Collection<int, Accommodation>  $nightly
     * @return Collection<int, array{date: string, accommodation_id: int, unit: string, value: float}>
     */
    private function soldNights(Collection $confirmed, Collection $nightly): Collection
    {
        $unitOf = $nightly->mapWithKeys(fn (Accommodation $a) => [$a->id => $a->shared_unit ? "u:{$a->shared_unit}" : "a:{$a->id}"]);
        $rows = collect();

        foreach ($confirmed->filter(fn (Booking $b) => ! $b->isHourly()) as $booking) {
            $count = max(1, $booking->nights());
            foreach ($booking->accommodations as $accommodation) {
                if (! isset($unitOf[$accommodation->id])) {
                    continue;
                }
                $value = $accommodation->pivot->subtotal / $count;
                foreach (CarbonPeriod::create($booking->check_in, $booking->check_out->subDay()) as $night) {
                    $rows->push(['date' => $night->toDateString(), 'accommodation_id' => $accommodation->id, 'unit' => $unitOf[$accommodation->id], 'value' => $value]);
                }
            }
        }

        return $rows;
    }

    /** Imóveis que podem ser ocupados numa noite (os do mesmo imóvel contam uma vez). */
    private function unitCount(Collection $nightly): int
    {
        return $nightly->map(fn (Accommodation $a) => $a->shared_unit ? "u:{$a->shared_unit}" : "a:{$a->id}")->unique()->count();
    }

    /**
     * @return array<string, int|float|null>
     */
    private function kpis(Collection $nights, Collection $confirmed, Collection $all, CarbonImmutable $from, CarbonImmutable $to, int $units, CarbonImmutable $today): array
    {
        [$f, $t] = [$from->toDateString(), $to->toDateString()];
        $inRange = fn (string $date) => $date >= $f && $date < $t;

        $periodNights = $nights->filter(fn ($n) => $inRange($n['date']));
        $events = $confirmed->filter(fn (Booking $b) => $b->isHourly() && $inRange($b->check_in->toDateString()));
        $stayRevenue = $periodNights->sum('value');
        $soldUnitNights = $periodNights->map(fn ($n) => $n['unit'].'|'.$n['date'])->unique()->count();
        $available = $units * (int) $from->diffInDays($to);

        $stays = $confirmed->filter(fn (Booking $b) => ! $b->isHourly() && $inRange($b->check_in->toDateString()));
        $created = $all->filter(fn (Booking $b) => $inRange($b->created_at->setTimezone($today->timezone)->toDateString()));
        // Pedidos decididos: aceitos (confirmados, mesmo que cancelados depois), recusados e vencidos sem resposta.
        $accepted = $created->filter(fn (Booking $b) => in_array($b->status, [BookingStatus::Confirmed, BookingStatus::Cancelled], true) && $b->confirmed_at)->count();
        $declined = $created->filter(fn (Booking $b) => $b->status === BookingStatus::Declined
            || ($b->status === BookingStatus::Pending && $b->check_in->lt($today)))->count();

        return [
            'revenue' => (int) round($stayRevenue + $events->sum('total')),
            'occupancy' => $available > 0 ? round($soldUnitNights / $available * 100, 1) : null,
            'adr' => $periodNights->count() > 0 ? (int) round($stayRevenue / $periodNights->count()) : null,
            'bookings' => $stays->count() + $events->count(),
            'nights_sold' => $periodNights->count(),
            'avg_nights' => $stays->count() ? round($stays->avg(fn (Booking $b) => $b->nights()), 1) : null,
            'lead_days' => $stays->count() ? (int) round($stays->avg(fn (Booking $b) => max(0, $b->created_at->setTimezone($today->timezone)->startOfDay()->diffInDays($b->check_in, false)))) : null,
            'conversion' => $accepted + $declined > 0 ? (int) round($accepted / ($accepted + $declined) * 100) : null,
            'requests' => $created->count(),
            'cancellations' => $all->filter(fn (Booking $b) => $b->status === BookingStatus::Cancelled && $b->cancelled_at && $inRange($b->cancelled_at->setTimezone($today->timezone)->toDateString()))->count(),
            'events' => $events->count(),
            'hours_sold' => $events->sum(fn (Booking $b) => $b->hours()),
        ];
    }

    /**
     * Últimos 12 meses (até o atual): receita e ocupação de cada mês.
     *
     * @return list<array{month: string, revenue: int, occupancy: float|null}>
     */
    private function monthly(Collection $nights, Collection $confirmed, CarbonImmutable $today, int $units): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $start = $today->startOfMonth()->subMonths($i);
            $key = $start->format('Y-m');
            $monthNights = $nights->filter(fn ($n) => str_starts_with($n['date'], $key));
            $events = $confirmed->filter(fn (Booking $b) => $b->isHourly() && $b->check_in->format('Y-m') === $key);
            $available = $units * $start->daysInMonth;
            $months[] = [
                'month' => $key,
                'revenue' => (int) round($monthNights->sum('value') + $events->sum('total')),
                'occupancy' => $available ? round($monthNights->map(fn ($n) => $n['unit'].'|'.$n['date'])->unique()->count() / $available * 100, 1) : null,
            ];
        }

        return $months;
    }

    /**
     * @return list<array{id: int, name: string, revenue: int, nights: int, hours: int, hourly: bool}>
     */
    private function byAccommodation(Collection $nights, Collection $confirmed, Collection $accommodations, CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$f, $t] = [$from->toDateString(), $to->toDateString()];
        $events = $confirmed->filter(fn (Booking $b) => $b->isHourly() && $b->check_in->toDateString() >= $f && $b->check_in->toDateString() < $t);
        $periodNights = $nights->filter(fn ($n) => $n['date'] >= $f && $n['date'] < $t)->groupBy('accommodation_id');

        return $accommodations->map(function (Accommodation $a) use ($periodNights, $events) {
            $own = $events->filter(fn (Booking $b) => $b->accommodations->contains('id', $a->id));

            return [
                'id' => $a->id,
                'name' => $a->name,
                'hourly' => $a->isHourly(),
                'revenue' => (int) round(($periodNights[$a->id] ?? collect())->sum('value') + $own->sum(fn (Booking $b) => $b->accommodations->firstWhere('id', $a->id)->pivot->subtotal)),
                'nights' => ($periodNights[$a->id] ?? collect())->count(),
                'hours' => $own->sum(fn (Booking $b) => $b->hours()),
            ];
        })->sortByDesc('revenue')->values()->all();
    }

    /**
     * Entradas por dia da semana (0 = domingo), estadias e eventos confirmados do período.
     *
     * @return list<int>
     */
    private function weekdays(Collection $confirmed, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = array_fill(0, 7, 0);
        foreach ($confirmed as $booking) {
            if ($booking->check_in->gte($from) && $booking->check_in->lt($to)) {
                $counts[$booking->check_in->dayOfWeek]++;
            }
        }

        return $counts;
    }

    /**
     * Horário previsto de chegada das estadias confirmadas do período, por hora cheia.
     *
     * @return list<array{hour: int, count: int}>
     */
    private function arrivals(Collection $confirmed, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = [];
        foreach ($confirmed as $booking) {
            if ($booking->arrival_time && $booking->check_in->gte($from) && $booking->check_in->lt($to)) {
                $hour = intdiv(Booking::minutes($booking->arrival_time), 60);
                $counts[$hour] = ($counts[$hour] ?? 0) + 1;
            }
        }
        ksort($counts);

        return array_map(fn ($hour, $count) => ['hour' => $hour, 'count' => $count], array_keys($counts), $counts);
    }
}
