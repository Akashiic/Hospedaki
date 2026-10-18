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
        $this->assertSame(0, $quote['extra_guests']);
        $this->assertSame(0, $quote['extra_guests_subtotal']);
        $this->assertSame(0, $quote['extra_guests_unpriced']);
    }

    public function test_priced_extra_guests_are_charged_every_night(): void
    {
        // 2 pessoas na diária, R$ 50 por pessoa a mais: 4 hóspedes em 2 noites somam R$ 200.
        $accommodation = Accommodation::factory()->create([
            'base_price' => 30000, 'max_guests' => 6, 'included_guests' => 2, 'extra_guest_price' => 5000,
        ]);

        // 2030-01-01 é terça: duas noites de preço base.
        $quote = app(PricingService::class)->quote(
            $accommodation, CarbonImmutable::parse('2030-01-01'), CarbonImmutable::parse('2030-01-03'), 2
        );

        $this->assertSame([40000, 40000], array_column($quote['nights'], 'price'));
        $this->assertSame(80000, $quote['total']);
        $this->assertSame(2, $quote['extra_guests']);
        $this->assertSame(20000, $quote['extra_guests_subtotal']);
        $this->assertSame(0, $quote['extra_guests_unpriced']);
    }

    public function test_unknown_extra_guest_price_is_left_out_and_reported(): void
    {
        $accommodation = Accommodation::factory()->create([
            'base_price' => 30000, 'max_guests' => 6, 'included_guests' => 2, 'extra_guest_price' => null,
        ]);

        $quote = app(PricingService::class)->quote(
            $accommodation, CarbonImmutable::parse('2030-01-01'), CarbonImmutable::parse('2030-01-03'), 2
        );

        $this->assertSame([30000, 30000], array_column($quote['nights'], 'price'));
        $this->assertSame(60000, $quote['total']);
        $this->assertSame(2, $quote['extra_guests']);
        $this->assertSame(0, $quote['extra_guests_subtotal']);
        $this->assertSame(2, $quote['extra_guests_unpriced']);
    }
}
