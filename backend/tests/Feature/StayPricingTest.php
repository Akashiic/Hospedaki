<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Taxa de limpeza, descontos de estadia longa e mínimo de noites das temporadas.
 */
class StayPricingTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $chale;

    protected function setUp(): void
    {
        parent::setUp();

        $property = Property::factory()->create(['slug' => 'pousada']);
        $this->chale = Accommodation::factory()->for($property)->create([
            'base_price' => 10000, 'weekend_price' => null, 'max_guests' => 4,
            'cleaning_fee' => 5000, 'weekly_discount' => 10, 'monthly_discount' => 25,
        ]);
    }

    private function quote(int $nights, int $from = 30): TestResponse
    {
        $in = CarbonImmutable::now()->addDays($from);

        return $this->postJson('/api/properties/pousada/quote', [
            'accommodation_ids' => [$this->chale->id],
            'check_in' => $in->toDateString(),
            'check_out' => $in->addDays($nights)->toDateString(),
            'guests' => 2,
        ]);
    }

    public function test_short_stay_pays_nights_plus_cleaning(): void
    {
        $this->quote(3)->assertOk()
            ->assertJsonPath('total', 35000)
            ->assertJsonPath('items.0.nights_total', 30000)
            ->assertJsonPath('items.0.discount', 0)
            ->assertJsonPath('items.0.cleaning_fee', 5000);
    }

    public function test_weekly_and_monthly_discounts_apply_to_the_nights_only(): void
    {
        // 7 noites: 70.000 - 10% + 5.000 de limpeza.
        $this->quote(7)->assertOk()->assertJsonPath('total', 68000)->assertJsonPath('items.0.discount_percent', 10);
        // 28 noites: o mensal (25%) toma o lugar do semanal.
        $this->quote(28)->assertOk()->assertJsonPath('total', 215000)->assertJsonPath('items.0.discount', 70000);
    }

    public function test_a_season_can_require_more_nights(): void
    {
        $start = CarbonImmutable::now()->addDays(30);
        $this->chale->seasonalPrices()->create([
            'name' => 'Réveillon', 'starts_on' => $start->toDateString(), 'ends_on' => $start->addDays(2)->toDateString(),
            'price' => 20000, 'min_nights' => 3,
        ]);

        $this->quote(2)->assertUnprocessable()->assertJsonValidationErrors('check_out');
        $this->quote(3)->assertOk()->assertJsonPath('items.0.nights_total', 60000);
        // Fora da temporada, vale o mínimo normal.
        $this->quote(2, 60)->assertOk();
    }

    public function test_booking_total_keeps_the_fee_and_discount(): void
    {
        $in = CarbonImmutable::now()->addDays(30);
        $this->postJson('/api/properties/pousada/booking-requests', [
            'accommodation_ids' => [$this->chale->id],
            'check_in' => $in->toDateString(),
            'check_out' => $in->addDays(7)->toDateString(),
            'guests' => 2, 'guest_name' => 'Maria', 'guest_phone' => '11999999999',
        ])->assertCreated()->assertJsonPath('data.total', 68000);
    }
}
