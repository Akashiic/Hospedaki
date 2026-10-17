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
 * Noites de sexta e sábado contam como fim de semana.
 */
class PricingService
{
    public const WEEKEND_NIGHTS = [CarbonInterface::FRIDAY, CarbonInterface::SATURDAY];

    /**
     * @return array{total: int, nights: list<array{date: string, price: int}>}
     */
    public function quote(Accommodation $accommodation, CarbonInterface $checkIn, CarbonInterface $checkOut): array
    {
        $checkIn = CarbonImmutable::parse($checkIn)->startOfDay();
        $checkOut = CarbonImmutable::parse($checkOut)->startOfDay();

        $seasons = $accommodation->seasonalPrices()
            ->whereDate('starts_on', '<', $checkOut)
            ->whereDate('ends_on', '>=', $checkIn)
            ->orderByDesc('starts_on')
            ->get();

        $nights = [];
        $total = 0;

        foreach (CarbonPeriod::create($checkIn, $checkOut->subDay()) as $night) {
            $price = $this->priceForNight($accommodation, $seasons->first(
                fn (SeasonalPrice $season) => $night->betweenIncluded($season->starts_on, $season->ends_on)
            ), $night);

            $nights[] = ['date' => $night->toDateString(), 'price' => $price];
            $total += $price;
        }

        return ['total' => $total, 'nights' => $nights];
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
