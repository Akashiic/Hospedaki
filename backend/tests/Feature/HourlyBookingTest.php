<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Horários nas reservas: chegada prevista numa estadia e reserva por hora (day use, eventos).
 * O salão fica no mesmo imóvel do espaço (shared_unit): um evento ocupa a noite do espaço.
 */
class HourlyBookingTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    private Accommodation $hall;

    private Accommodation $espaco;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->property = Property::factory()->create(['slug' => 'pousada']);
        $this->hall = Accommodation::factory()->for($this->property)->create([
            'shared_unit' => 'espaco', 'booking_mode' => Accommodation::HOURLY, 'max_guests' => 40,
            'base_price' => 0, 'hourly_price' => 15000, 'min_hours' => 4, 'opens_at' => '09:00', 'closes_at' => '23:00', 'cleaning_fee' => 20000,
        ]);
        $this->espaco = Accommodation::factory()->for($this->property)->create(['shared_unit' => 'espaco', 'max_guests' => 6]);
    }

    private function day(int $days = 10): string
    {
        return $this->property->today()->addDays($days)->toDateString();
    }

    private function hourly(string $start, string $end, int $days = 10, array $extra = []): TestResponse
    {
        return $this->postJson('/api/properties/pousada/booking-requests', $extra + [
            'accommodation_ids' => [$this->hall->id],
            'check_in' => $this->day($days),
            'start_time' => $start,
            'end_time' => $end,
            'guests' => 25,
            'guest_name' => 'Júlia',
            'guest_phone' => '11999990000',
        ]);
    }

    public function test_quote_charges_hours_plus_cleaning(): void
    {
        $quote = $this->postJson('/api/properties/pousada/quote', [
            'accommodation_ids' => [$this->hall->id], 'check_in' => $this->day(), 'start_time' => '14:00', 'end_time' => '20:00', 'guests' => 25,
        ])->assertOk()->json();

        $this->assertSame([6, 6 * 15000 + 20000, 0], [$quote['hours'], $quote['total'], $quote['nights']]);
        $this->assertSame(15000, $quote['items'][0]['hourly_price']);
    }

    public function test_booking_by_the_hour_stores_the_times(): void
    {
        $booking = $this->hourly('14:00', '20:00')->assertCreated()->json('data');

        $this->assertSame(['14:00', '20:00', 6], [$booking['start_time'], $booking['end_time'], $booking['hours']]);
        $this->assertSame($booking['check_in'], $booking['check_out']);
    }

    public function test_hourly_rules(): void
    {
        $this->hourly('14:00', '16:00')->assertJsonValidationErrors('end_time');          // mínimo de 4 horas
        $this->hourly('14:00', '18:30')->assertJsonValidationErrors('end_time');          // horas inteiras
        $this->hourly('07:00', '12:00')->assertJsonValidationErrors('start_time');        // antes de abrir
        $this->hall->update(['closes_at' => '22:00']);
        $this->hourly('19:00', '23:00')->assertJsonValidationErrors('start_time');        // depois de fechar
        $this->hourly('14:00', '18:00', extra: ['guests' => 41])->assertJsonValidationErrors('guests');
        $this->hourly('14:00', '18:00', days: -1)->assertJsonValidationErrors('check_in'); // ontem

        $this->postJson('/api/properties/pousada/quote', [
            'accommodation_ids' => [$this->hall->id, $this->espaco->id], 'check_in' => $this->day(), 'start_time' => '14:00', 'end_time' => '18:00', 'guests' => 5,
        ])->assertJsonValidationErrors('accommodation_ids');
    }

    public function test_overlapping_hours_are_refused_but_the_same_day_is_still_open(): void
    {
        $this->hourly('14:00', '18:00')->assertCreated();

        $this->hourly('17:00', '21:00')->assertJsonValidationErrors('start_time');
        $this->hourly('09:00', '14:00')->assertCreated(); // termina quando a outra começa

        $hours = $this->getJson("/api/properties/pousada/hours?accommodation_id={$this->hall->id}&date={$this->day()}")->assertOk()->json();
        $this->assertSame([['start' => '09:00', 'end' => '14:00'], ['start' => '14:00', 'end' => '18:00']], $hours['taken']);
        $this->assertFalse($hours['closed']);
        $this->assertSame(['09:00', '23:00', 4], [$hours['opens_at'], $hours['closes_at'], $hours['min_hours']]);

        // Das 18h às 23h ainda cabem 4 horas: o dia continua livre no calendário.
        $this->assertNotContains($this->day(), $this->unavailable($this->hall));
        $this->hourly('18:00', '22:00')->assertCreated();
        // Sobrou só 1 hora: o dia sai do calendário.
        $this->assertContains($this->day(), $this->unavailable($this->hall));
    }

    public function test_event_takes_the_night_of_the_shared_space_and_vice_versa(): void
    {
        $this->hourly('14:00', '20:00')->assertCreated();

        // Uma estadia no espaço que passa por aquela noite não cabe mais.
        $this->assertContains($this->day(), $this->unavailable($this->espaco));
        $this->postJson('/api/properties/pousada/booking-requests', [
            'accommodation_ids' => [$this->espaco->id], 'check_in' => $this->day(), 'check_out' => $this->day(11),
            'guests' => 2, 'guest_name' => 'Ana', 'guest_phone' => '11999990000',
        ])->assertJsonValidationErrors('check_in');

        // E com uma estadia no espaço, o salão fica fechado naquele dia.
        $this->postJson('/api/properties/pousada/booking-requests', [
            'accommodation_ids' => [$this->espaco->id], 'check_in' => $this->day(20), 'check_out' => $this->day(22),
            'guests' => 2, 'guest_name' => 'Ana', 'guest_phone' => '11999990000',
        ])->assertCreated();
        $this->hourly('14:00', '18:00', days: 21)->assertJsonValidationErrors('start_time');
        $this->assertTrue($this->getJson("/api/properties/pousada/hours?accommodation_id={$this->hall->id}&date={$this->day(21)}")->json('closed'));
    }

    public function test_stay_keeps_the_guest_arrival_time(): void
    {
        $booking = $this->postJson('/api/properties/pousada/booking-requests', [
            'accommodation_ids' => [$this->espaco->id], 'check_in' => $this->day(), 'check_out' => $this->day(12),
            'guests' => 2, 'guest_name' => 'Ana', 'guest_phone' => '11999990000', 'arrival_time' => '16:30',
        ])->assertCreated()->json('data');

        $this->assertSame(['16:30', null, 0], [$booking['arrival_time'], $booking['start_time'], $booking['hours']]);
        $this->postJson('/api/properties/pousada/booking-requests', [
            'accommodation_ids' => [$this->espaco->id], 'check_in' => $this->day(30), 'check_out' => $this->day(31),
            'guests' => 2, 'guest_name' => 'Ana', 'guest_phone' => '11999990000', 'arrival_time' => '25:00',
        ])->assertJsonValidationErrors('arrival_time');
    }

    public function test_site_shows_mode_and_times(): void
    {
        $this->property->update(['check_in_time' => '14:00']);
        $this->espaco->update(['check_out_time' => '11:00']);

        $stays = collect($this->getJson('/api/properties/pousada')->json('data.accommodations'))->keyBy('id');

        $this->assertSame(['hourly', 15000, '09:00', '23:00'], [$stays[$this->hall->id]['booking_mode'], $stays[$this->hall->id]['hourly_price'], $stays[$this->hall->id]['opens_at'], $stays[$this->hall->id]['closes_at']]);
        // Sem horário próprio, vale o da pousada.
        $this->assertSame(['14:00', '11:00'], [$stays[$this->espaco->id]['check_in_time'], $stays[$this->espaco->id]['check_out_time']]);
    }

    public function test_owner_sees_times_in_the_panel(): void
    {
        $this->hourly('14:00', '20:00')->assertCreated();
        $this->actingAs(User::factory()->create(['property_id' => $this->property->id]), 'sanctum');

        $booking = $this->getJson('/api/admin/bookings?scope=all')->assertOk()->json('data.0');
        $this->assertSame(['14:00', '20:00', 6], [$booking['start_time'], $booking['end_time'], $booking['hours']]);
        $this->assertSame(1, Booking::whereNotNull('start_time')->count());
    }

    /** @return list<string> */
    private function unavailable(Accommodation $accommodation): array
    {
        $response = $this->getJson('/api/properties/pousada/availability?from='.$this->day(0).'&to='.$this->day(40))->assertOk();

        return collect($response->json('accommodations'))->firstWhere('id', $accommodation->id)['unavailable'];
    }

    public function test_event_is_ongoing_on_its_day_and_completed_after(): void
    {
        $booking = $this->hourly('14:00', '20:00', days: 2)->assertCreated()->json('data');
        Booking::where('code', $booking['code'])->update(['status' => 'confirmed']);
        $this->actingAs(User::factory()->create(['property_id' => $this->property->id]), 'sanctum');

        $counts = fn () => $this->getJson('/api/admin/bookings?scope=all')->json('meta.counts');
        $this->assertSame(1, $counts()['upcoming']);

        $this->travelTo($this->property->today()->addDays(2)->setTime(10, 0));
        $this->assertSame([1, 0], [$counts()['ongoing'], $counts()['completed']]);

        $this->travelTo($this->property->today()->addDay()->setTime(10, 0));
        $this->assertSame([0, 1, 1], [$counts()['ongoing'], $counts()['completed'], $counts()['past']]);
    }
}
