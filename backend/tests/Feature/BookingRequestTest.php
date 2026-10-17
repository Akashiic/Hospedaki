<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingRequestTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    private Accommodation $chale;

    private Accommodation $espaco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::factory()->create(['slug' => 'pousada']);
        $this->chale = Accommodation::factory()->for($this->property)->create(['base_price' => 30000, 'max_guests' => 2]);
        $this->espaco = Accommodation::factory()->for($this->property)->create(['base_price' => 50000, 'max_guests' => 6]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'accommodation_ids' => [$this->chale->id],
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'guests' => 2,
            'guest_name' => 'Maria',
            'guest_phone' => '11999999999',
        ];
    }

    public function test_public_property_page(): void
    {
        $this->getJson('/api/properties/pousada')
            ->assertOk()
            ->assertJsonCount(2, 'data.accommodations');

        $this->getJson('/api/properties/nao-existe')->assertNotFound();
    }

    public function test_guest_can_request_both_accommodations_together(): void
    {
        $response = $this->postJson('/api/properties/pousada/booking-requests', $this->payload([
            'accommodation_ids' => [$this->chale->id, $this->espaco->id],
            'guests' => 6,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', 2 * 30000 + 2 * 50000)
            ->assertJsonMissingPath('data.guest_phone');
    }

    public function test_pending_request_holds_dates_and_calendars_are_separate(): void
    {
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())->assertCreated();

        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('check_in');

        $this->postJson('/api/properties/pousada/booking-requests', $this->payload([
            'accommodation_ids' => [$this->espaco->id],
        ]))->assertCreated();

        // Check-out no mesmo dia do check-in de outra reserva é permitido.
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload([
            'check_in' => now()->addDays(12)->toDateString(),
            'check_out' => now()->addDays(13)->toDateString(),
        ]))->assertCreated();
    }

    public function test_blocked_dates_show_in_calendar_and_prevent_booking(): void
    {
        $day = now()->addDays(11)->toDateString();
        $this->chale->blockedDates()->create(['starts_on' => $day, 'ends_on' => $day]);

        $this->getJson('/api/properties/pousada/availability?from='.now()->toDateString().'&to='.now()->addDays(30)->toDateString())
            ->assertOk()
            ->assertJsonPath('accommodations.0.unavailable', [$day]);

        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())->assertUnprocessable();
    }

    public function test_owner_confirms_only_own_bookings(): void
    {
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())->assertCreated();
        $booking = Booking::first();

        $other = User::factory()->create(['property_id' => Property::factory()->create()->id]);
        Sanctum::actingAs($other);
        $this->postJson("/api/admin/bookings/{$booking->id}/confirm")->assertNotFound();

        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
        $this->postJson("/api/admin/bookings/{$booking->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.guest_phone', '11999999999');
    }

    public function test_declining_frees_the_dates(): void
    {
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())->assertCreated();

        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
        $this->postJson('/api/admin/bookings/'.Booking::first()->id.'/decline')->assertJsonPath('data.status', 'declined');

        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())->assertCreated();
    }
}
