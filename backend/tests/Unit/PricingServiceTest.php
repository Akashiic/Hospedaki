<?php

namespace Tests\Unit;

use App\Models\Accommodation;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekday_weekend_and_season_prices(): void
    {
        $accommodation = Accommodation::factory()->create(['base_price' => 30000, 'weekend_price' => 40000]);
        $accommodation->seasonalPrices()->create([
            'name' => 'Feriado', 'starts_on' => '2030-01-07', 'ends_on' => '2030-01-07', 'price' => 90000,
        ]);

        // 2030-01-03 é quinta: noites de qui (base), sex e sáb (fim de semana), dom (base), seg (temporada).
        $quote = app(PricingService::class)->quote(
            $accommodation, CarbonImmutable::parse('2030-01-03'), CarbonImmutable::parse('2030-01-08')
        );

        $this->assertSame([30000, 40000, 40000, 30000, 90000], array_column($quote['nights'], 'price'));
        $this->assertSame(230000, $quote['total']);
    }
}
