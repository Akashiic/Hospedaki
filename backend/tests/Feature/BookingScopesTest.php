<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Filtros da aba Reservas do painel, com "hoje" no fuso da pousada.
 */
class BookingScopesTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    private Accommodation $accommodation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::factory()->create();
        $this->accommodation = Accommodation::factory()->for($this->property)->create();
        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));

        $this->booking('futura', BookingStatus::Confirmed, 5, 7);
        $this->booking('hospedada', BookingStatus::Confirmed, -1, 2);
        $this->booking('concluida', BookingStatus::Confirmed, -6, -3);
        $this->booking('pedido', BookingStatus::Pending, 3, 4);
        $this->booking('vencido', BookingStatus::Pending, -2, 1);
        $this->booking('recusada', BookingStatus::Declined, 8, 9);
        $this->booking('cancelada-antiga', BookingStatus::Cancelled, -9, -8);
    }

    private function booking(string $name, BookingStatus $status, int $in, int $out): void
    {
        $today = $this->property->today();
        $booking = $this->property->bookings()->create([
            'status' => $status, 'check_in' => $today->addDays($in)->toDateString(), 'check_out' => $today->addDays($out)->toDateString(),
            'guests' => 2, 'guest_name' => $name, 'guest_phone' => '11999999999', 'total' => 10000,
        ]);
        $booking->accommodations()->attach($this->accommodation->id, ['subtotal' => 10000]);
    }

    private function names(string $scope): array
    {
        return array_column($this->getJson("/api/admin/bookings?scope={$scope}")->assertOk()->json('data'), 'guest_name');
    }

    public function test_each_filter_returns_its_bookings(): void
    {
        $this->assertSame(['pedido'], $this->names('requests'));
        $this->assertSame(['futura'], $this->names('upcoming'));
        $this->assertSame(['hospedada'], $this->names('ongoing'));
        $this->assertSame(['concluida'], $this->names('completed'));
        // Já passou: a concluída, a cancelada antiga e o pedido que venceu sem resposta.
        $this->assertEqualsCanonicalizing(['concluida', 'cancelada-antiga', 'vencido'], $this->names('past'));
        $this->assertEqualsCanonicalizing(['recusada', 'cancelada-antiga'], $this->names('cancelled'));
        $this->assertCount(7, $this->names('all'));
    }

    public function test_past_lists_come_newest_first_and_counts_cover_every_filter(): void
    {
        $response = $this->getJson('/api/admin/bookings?scope=cancelled')->assertOk();

        $this->assertSame(['recusada', 'cancelada-antiga'], array_column($response->json('data'), 'guest_name'));
        $this->assertSame(
            ['requests' => 1, 'upcoming' => 1, 'ongoing' => 1, 'completed' => 1, 'past' => 3, 'cancelled' => 2, 'all' => 7],
            $response->json('meta.counts'),
        );
    }

    public function test_unknown_filter_is_rejected_and_other_properties_stay_hidden(): void
    {
        $this->getJson('/api/admin/bookings?scope=tudo')->assertUnprocessable();

        Booking::query()->update(['property_id' => Property::factory()->create()->id]);
        $this->assertSame([], $this->names('all'));
    }
}
