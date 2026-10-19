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
 *
 * Sobre a soma das diárias (com as pessoas a mais), como no Airbnb:
 *   - desconto mensal (monthly_discount %) a partir de 28 noites, senão semanal (weekly_discount %) a partir de 7;
 *   - taxa de limpeza (cleaning_fee), uma vez por estadia, sem desconto.
 */
class PricingService
{
    public const WEEKEND_NIGHTS = [CarbonInterface::FRIDAY, CarbonInterface::SATURDAY];

    /**
     * @param  int  $extraGuests  pessoas além das incluídas na diária, já distribuídas para esta acomodação
     * @return array{total: int, nights_total: int, discount: int, discount_percent: int, cleaning_fee: int, nights: list<array{date: string, price: int}>, extra_guests: int, extra_guests_subtotal: int, extra_guests_unpriced: int}
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

        $percent = $this->discountPercent($accommodation, count($nights));
        $discount = intdiv($total * $percent, 100);
        $cleaning = $accommodation->cleaning_fee ?? 0;

        return [
            'total' => $total - $discount + $cleaning,
            'nights_total' => $total,
            'discount' => $discount,
            'discount_percent' => $percent,
            'cleaning_fee' => $cleaning,
            'nights' => $nights,
            'extra_guests' => $extraGuests,
            'extra_guests_subtotal' => $extraTotal,
            'extra_guests_unpriced' => $priced ? 0 : $extraGuests,
        ];
    }

    /**
     * Desconto por estadia longa: o mensal vale a partir de 28 noites, o semanal a partir de 7.
     */
    public function discountPercent(Accommodation $accommodation, int $nights): int
    {
        if ($nights >= 28 && $accommodation->monthly_discount) {
            return min(100, $accommodation->monthly_discount);
        }

        return $nights >= 7 && $accommodation->weekly_discount ? min(100, $accommodation->weekly_discount) : 0;
    }

    /**
     * Maior mínimo de noites das temporadas que a estadia pega (0 quando nenhuma exige).
     */
    public function seasonMinNights(Accommodation $accommodation, CarbonInterface $checkIn, CarbonInterface $checkOut): int
    {
        return (int) $accommodation->seasonalPrices()
            ->whereDate('starts_on', '<', $checkOut)
            ->whereDate('ends_on', '>=', $checkIn)
            ->max('min_nights');
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
