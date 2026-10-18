<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regras do pedido pela API e isolamento entre pousadas.
 */
class BookingRulesTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    private Accommodation $chale;

    private Accommodation $alheia;

    protected function setUp(): void
    {
        parent::setUp();

        // Segunda, 19/10/2026 em Brasília.
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00', 'UTC'));

        $this->property = Property::factory()->create(['slug' => 'pousada']);
        $this->chale = Accommodation::factory()->for($this->property)->create(['base_price' => 30000, 'weekend_price' => 40000, 'max_guests' => 2]);
        $this->alheia = Accommodation::factory()->for(Property::factory()->create())->create(['max_guests' => 6]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'accommodation_ids' => [$this->chale->id],
            'check_in' => '2026-10-26',
            'check_out' => '2026-10-28',
            'guests' => 2,
            'guest_name' => 'Maria',
            'guest_phone' => '11999999999',
        ];
    }

    public function test_accommodation_from_another_property_or_inactive_is_not_found(): void
    {
        $inativa = Accommodation::factory()->for($this->property)->create(['active' => false]);

        foreach ([$this->alheia->id, $inativa->id] as $id) {
            foreach (['quote', 'booking-requests'] as $endpoint) {
                $this->postJson("/api/properties/pousada/{$endpoint}", $this->payload(['accommodation_ids' => [$this->chale->id, $id]]))
                    ->assertUnprocessable()
                    ->assertJsonPath('errors.accommodation_ids.0', 'Acomodação não encontrada.');
            }
        }

        $this->assertSame(0, Booking::count());
    }

    public function test_owner_cannot_block_or_unblock_another_propertys_dates(): void
    {
        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
        $block = ['starts_on' => '2026-11-01', 'ends_on' => '2026-11-02'];

        $this->postJson('/api/admin/blocked-dates', ['accommodation_id' => $this->alheia->id] + $block)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accommodation_id');
        $this->postJson('/api/admin/blocked-dates', ['accommodation_id' => $this->chale->id] + $block)->assertCreated();

        $outro = $this->alheia->blockedDates()->create($block);
        $this->deleteJson("/api/admin/blocked-dates/{$outro->id}")->assertNotFound();
        $this->assertTrue(BlockedDate::whereKey($outro->id)->exists());
    }

    public function test_minimum_nights_is_enforced(): void
    {
        $this->chale->update(['min_nights' => 2]);

        $this->postJson('/api/properties/pousada/quote', $this->payload(['check_out' => '2026-10-27']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.check_out.0', "{$this->chale->name} exige no mínimo 2 diárias.");

        $this->postJson('/api/properties/pousada/quote', $this->payload())->assertOk();
    }

    public function test_guests_above_capacity_are_refused(): void
    {
        $this->postJson('/api/properties/pousada/quote', $this->payload(['guests' => 3]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.guests.0', 'Número de hóspedes acima da capacidade.');
    }

    public function test_seasonal_price_applies_through_the_quote(): void
    {
        // Temporada só na noite de quinta, 29/10; quarta, 28/10 fica no preço base.
        $this->chale->seasonalPrices()->create(['name' => 'Feriado', 'starts_on' => '2026-10-29', 'ends_on' => '2026-10-29', 'price' => 90000]);

        $this->postJson('/api/properties/pousada/quote', $this->payload(['check_in' => '2026-10-28', 'check_out' => '2026-10-30']))
            ->assertOk()
            ->assertJsonPath('items.0.nights', [['date' => '2026-10-28', 'price' => 30000], ['date' => '2026-10-29', 'price' => 90000]])
            ->assertJsonPath('total', 120000);
    }

    public function test_bookings_are_accepted_only_for_the_next_two_years(): void
    {
        $this->postJson('/api/properties/pousada/quote', $this->payload(['check_in' => '2028-10-17', 'check_out' => '2028-10-19']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.check_out.0', 'Só aceitamos reservas para os próximos dois anos.');

        $this->postJson('/api/properties/pousada/quote', $this->payload(['check_in' => '2028-10-16', 'check_out' => '2028-10-18']))
            ->assertOk();
    }
}
