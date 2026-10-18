<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\SeasonalPrice;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Calcula o valor de uma estadia, diária por diária.
 *
 * Ordem de prioridade para cada noite:
 *   1. preço de temporada (com valor de fim de semana, se houver)
 *   2. preço de fim de semana da acomodação
 *   3. preço base
 *
 * Noites de sexta e sábado contam como fim de semana. Pessoas além das incluídas na diária
 * somam o acréscimo por pessoa (extra_guest_price) em cada noite. Se a pousada não informou
 * esse valor, ele não entra no total e as pessoas voltam em "extra_guests_unpriced".
 */
class PricingService
{
    public const WEEKEND_NIGHTS = [CarbonInterface::FRIDAY, CarbonInterface::SATURDAY];

    /**
     * @param  int  $extraGuests  pessoas além das incluídas na diária, já distribuídas para esta acomodação
     * @return array{total: int, nights: list<array{date: string, price: int}>, extra_guests: int, extra_guests_subtotal: int, extra_guests_unpriced: int}
     */
    public function quote(Accommodation $accommodation, CarbonInterface $checkIn, CarbonInterface $checkOut, int $extraGuests = 0): array
    {
        $checkIn = CarbonImmutable::parse($checkIn)->startOfDay();
        $checkOut = CarbonImmutable::parse($checkOut)->startOfDay();

        $seasons = $accommodation->seasonalPrices()
            ->whereDate('starts_on', '<', $checkOut)
            ->whereDate('ends_on', '>=', $checkIn)
            ->orderByDesc('starts_on')
            ->get();

        $extraGuests = max(0, $extraGuests);
        $priced = $accommodation->extra_guest_price !== null;
        $extraPerNight = $priced ? $extraGuests * $accommodation->extra_guest_price : 0;

        $nights = [];
        $total = 0;
        $extraTotal = 0;

        foreach (CarbonPeriod::create($checkIn, $checkOut->subDay()) as $night) {
            $price = $this->priceForNight($accommodation, $seasons->first(
                fn (SeasonalPrice $season) => $night->betweenIncluded($season->starts_on, $season->ends_on)
            ), $night) + $extraPerNight;

            $nights[] = ['date' => $night->toDateString(), 'price' => $price];
            $total += $price;
            $extraTotal += $extraPerNight;
        }

        return [
            'total' => $total,
            'nights' => $nights,
            'extra_guests' => $extraGuests,
            'extra_guests_subtotal' => $extraTotal,
            'extra_guests_unpriced' => $priced ? 0 : $extraGuests,
        ];
    }

    private function priceForNight(Accommodation $accommodation, ?SeasonalPrice $season, CarbonInterface $night): int
    {
        $isWeekend = in_array($night->dayOfWeek, self::WEEKEND_NIGHTS, true);

        if ($season) {
            return $isWeekend && $season->weekend_price ? $season->weekend_price : $season->price;
        }

        return $isWeekend && $accommodation->weekend_price ? $accommodation->weekend_price : $accommodation->base_price;
    }
}
