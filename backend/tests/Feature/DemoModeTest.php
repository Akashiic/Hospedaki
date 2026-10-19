<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::factory()->create(['slug' => 'pousada-demo']);
        Accommodation::factory()->for($this->property)->create(['base_price' => 30000, 'max_guests' => 6, 'sort_order' => 1]);
        Accommodation::factory()->for($this->property)->create(['base_price' => 50000, 'max_guests' => 2, 'sort_order' => 2]);

        config(['demo.enabled' => true, 'demo.property_slug' => 'pousada-demo']);
    }

    public function test_demo_login_is_closed_when_demo_mode_is_off(): void
    {
        config(['demo.enabled' => false]);

        $this->postJson('/api/admin/demo-login')->assertNotFound();
        $this->getJson('/api/properties/pousada-demo')->assertJsonPath('data.demo', false);
    }

    public function test_demo_login_opens_the_panel_with_sample_bookings(): void
    {
        Notification::fake();

        $token = $this->postJson('/api/admin/demo-login')->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/admin/me')->assertOk()->assertJsonPath('property.demo', true);

        $bookings = $this->withToken($token)->getJson('/api/admin/bookings')->assertOk()->json('data');
        $this->assertCount(4, $bookings);
        $this->assertSame(['declined', 'confirmed', 'pending', 'pending'], array_column($bookings, 'status'));
        $this->assertTrue(collect($bookings)->every(fn ($booking) => $booking['total'] > 0));
        $this->assertCount(2, $this->withToken($token)->getJson('/api/admin/blocked-dates')->json('data'));

        // Os exemplos não avisam o dono por e-mail.
        Notification::assertNothingSent();
    }

    public function test_site_shows_the_demo_flag_and_resets_once_a_day(): void
    {
        $this->getJson('/api/properties/pousada-demo')->assertOk()->assertJsonPath('data.demo', true);
        $this->assertSame(4, Booking::count());

        // Um visitante mexe na demo: recusa tudo e apaga os bloqueios.
        Booking::query()->update(['status' => BookingStatus::Declined]);
        BlockedDate::query()->delete();

        // No mesmo dia, a demo fica como o visitante deixou.
        $this->getJson('/api/properties/pousada-demo')->assertOk();
        $this->assertSame(0, Booking::where('status', BookingStatus::Pending)->count());

        // No dia seguinte (no fuso da pousada), volta ao estado inicial.
        $this->travelTo(CarbonImmutable::now($this->property->timezone)->addDay()->setTime(0, 30));
        $this->getJson('/api/properties/pousada-demo')->assertOk();
        $this->assertSame(2, Booking::where('status', BookingStatus::Pending)->count());
        $this->assertSame(2, BlockedDate::count());
    }

    public function test_other_properties_are_never_reset(): void
    {
        $other = Property::factory()->create(['slug' => 'pousada-de-verdade']);
        $accommodation = Accommodation::factory()->for($other)->create();
        $booking = $other->bookings()->create([
            'status' => BookingStatus::Pending, 'check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(4)->toDateString(),
            'guests' => 2, 'guest_name' => 'Maria', 'guest_phone' => '11999999999', 'total' => 10000,
        ]);
        $booking->accommodations()->attach($accommodation->id, ['subtotal' => 10000]);

        $this->getJson('/api/properties/pousada-de-verdade')->assertOk()->assertJsonPath('data.demo', false);
        $this->getJson('/api/properties/pousada-demo')->assertOk();

        $this->assertModelExists($booking);
    }

    public function test_demo_reset_command(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();
        $this->assertSame(4, $this->property->bookings()->count());

        config(['demo.enabled' => false]);
        $this->artisan('demo:reset')->assertFailed();
    }
}
