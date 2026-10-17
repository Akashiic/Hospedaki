<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Diz quais diárias de uma acomodação estão livres.
 * Uma diária é a noite que começa na data indicada.
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
    public function unavailableNights(Accommodation $accommodation, CarbonInterface $from, CarbonInterface $to, ?Booking $ignore = null): array
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();

        $taken = [];

        $bookings = $accommodation->bookings()
            ->whereIn('status', BookingStatus::holdingDates())
            ->whereDate('check_in', '<', $to)
            ->whereDate('check_out', '>', $from)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->get(['bookings.check_in', 'bookings.check_out']);

        foreach ($bookings as $booking) {
            $this->collect($taken, $booking->check_in, $booking->check_out->subDay(), $from, $to);
        }

        $blocks = $accommodation->blockedDates()
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
