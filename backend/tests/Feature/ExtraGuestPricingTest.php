<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Notifications\NewBookingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Diária para 2 pessoas e acréscimo por pessoa a mais, como no Espaço Bela Vista.
 */
class ExtraGuestPricingTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $espaco;

    private Accommodation $chale;

    protected function setUp(): void
    {
        parent::setUp();

        // Segunda, 19/10/2026. A estadia de sexta a domingo tem duas noites de fim de semana.
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00', 'UTC'));

        $property = Property::factory()->create(['slug' => 'pousada']);
        $prices = ['base_price' => 49700, 'weekend_price' => 99700, 'included_guests' => 2, 'extra_guest_price' => null];
        // O chalé é criado primeiro (id menor), mas vem depois na ordem do site.
        $this->chale = Accommodation::factory()->for($property)->create(['max_guests' => 4, 'sort_order' => 2] + $prices);
        $this->espaco = Accommodation::factory()->for($property)->create(['max_guests' => 6, 'sort_order' => 1] + $prices);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'accommodation_ids' => [$this->espaco->id],
            'check_in' => '2026-10-23',
            'check_out' => '2026-10-25',
            'guests' => 2,
            'guest_name' => 'Família Oliveira',
            'guest_phone' => '11999999999',
        ];
    }

    public function test_accommodation_exposes_guest_pricing(): void
    {
        $this->espaco->update(['extra_guest_price' => 5000]);

        $this->getJson('/api/properties/pousada')
            ->assertOk()
            ->assertJsonPath('data.accommodations.0.id', $this->espaco->id)
            ->assertJsonPath('data.accommodations.0.included_guests', 2)
            ->assertJsonPath('data.accommodations.0.extra_guest_price', 5000)
            ->assertJsonPath('data.accommodations.1.extra_guest_price', null);
    }

    public function test_couple_has_no_extra_guests(): void
    {
        $this->postJson('/api/properties/pousada/quote', $this->payload())
            ->assertOk()
            ->assertJsonPath('total', 199400)
            ->assertJsonPath('extra_guests_unpriced', 0)
            ->assertJsonPath('items.0.extra_guests', 0)
            ->assertJsonPath('items.0.extra_guests_subtotal', 0);
    }

    public function test_unknown_fee_is_left_out_of_the_total_and_reported(): void
    {
        $this->postJson('/api/properties/pousada/quote', $this->payload(['guests' => 6]))
            ->assertOk()
            ->assertJsonPath('total', 199400)
            ->assertJsonPath('extra_guests_unpriced', 4)
            ->assertJsonPath('items.0.subtotal', 199400)
            ->assertJsonPath('items.0.extra_guests', 4)
            ->assertJsonPath('items.0.extra_guests_subtotal', 0);
    }

    public function test_known_fee_is_added_to_every_night(): void
    {
        $this->espaco->update(['extra_guest_price' => 5000]);

        $this->postJson('/api/properties/pousada/quote', $this->payload(['guests' => 4]))
            ->assertOk()
            ->assertJsonPath('extra_guests_unpriced', 0)
            ->assertJsonPath('items.0.extra_guests', 2)
            ->assertJsonPath('items.0.extra_guests_subtotal', 20000)
            ->assertJsonPath('items.0.nights.0.price', 109700)
            ->assertJsonPath('items.0.subtotal', 219400)
            ->assertJsonPath('total', 219400);
    }

    public function test_without_included_guests_the_price_covers_full_capacity(): void
    {
        $this->espaco->update(['included_guests' => null]);

        $this->postJson('/api/properties/pousada/quote', $this->payload(['guests' => 6]))
            ->assertOk()
            ->assertJsonPath('extra_guests_unpriced', 0)
            ->assertJsonPath('items.0.extra_guests', 0);
    }

    public function test_extra_guests_fill_included_places_first_then_follow_the_site_order(): void
    {
        $both = ['accommodation_ids' => [$this->chale->id, $this->espaco->id]];
        $this->chale->update(['extra_guest_price' => 5000]);

        // 7 pessoas: 4 nas vagas incluídas (2 + 2) e 3 a mais, todas no Espaço (primeiro na ordem, até 6).
        $this->postJson('/api/properties/pousada/quote', $this->payload($both + ['guests' => 7]))
            ->assertOk()
            ->assertJsonPath('items.0.accommodation_id', $this->espaco->id)
            ->assertJsonPath('items.0.extra_guests', 3)
            ->assertJsonPath('items.1.extra_guests', 0)
            ->assertJsonPath('extra_guests_unpriced', 3)
            ->assertJsonPath('total', 2 * 199400);

        // 10 pessoas: o Espaço enche (4 a mais) e o Chalé fica com 2 a mais, que têm valor informado.
        $this->postJson('/api/properties/pousada/quote', $this->payload($both + ['guests' => 10]))
            ->assertOk()
            ->assertJsonPath('items.0.extra_guests', 4)
            ->assertJsonPath('items.1.accommodation_id', $this->chale->id)
            ->assertJsonPath('items.1.extra_guests', 2)
            ->assertJsonPath('items.1.extra_guests_subtotal', 2 * 2 * 5000)
            ->assertJsonPath('extra_guests_unpriced', 4)
            ->assertJsonPath('total', 2 * 199400 + 20000);
    }

    public function test_booking_keeps_the_unpriced_guests_for_the_panel_and_the_email(): void
    {
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload(['guests' => 6]))
            ->assertCreated()
            ->assertJsonPath('data.total', 199400)
            ->assertJsonPath('data.extra_guests_unpriced', 4);

        $booking = Booking::firstOrFail();
        $this->assertSame(4, $booking->extra_guests_unpriced);

        $owner = User::factory()->create(['property_id' => $booking->property_id]);
        Sanctum::actingAs($owner);
        $this->getJson('/api/admin/bookings?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.extra_guests_unpriced', 4);

        $mail = (new NewBookingRequest($booking))->toMail($owner);
        $text = (string) app(Markdown::class)->renderText('notifications::email', $mail->data());
        $this->assertStringContainsString('Hóspedes: 6 (4 a mais; acréscimo a combinar) · Total: R$ 1.994,00', $text);
        $this->assertStringContainsString('O total não inclui o acréscimo das pessoas a mais', $text);
        $this->assertStringContainsString('(2 diárias)', $text);
    }

    public function test_email_without_extra_guests_has_no_fee_note(): void
    {
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload(['check_out' => '2026-10-24']))
            ->assertCreated()
            ->assertJsonPath('data.extra_guests_unpriced', 0);

        $booking = Booking::firstOrFail();
        $mail = (new NewBookingRequest($booking))->toMail(User::factory()->create(['property_id' => $booking->property_id]));
        $text = (string) app(Markdown::class)->renderText('notifications::email', $mail->data());

        $this->assertStringContainsString('Hóspedes: 2 · Total: R$ 997,00', $text);
        $this->assertStringContainsString('(1 diária)', $text);
        $this->assertStringNotContainsString('acréscimo', $text);
    }
}
