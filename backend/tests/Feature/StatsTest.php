<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    private Accommodation $chale;

    private Accommodation $espaco;

    private Accommodation $apto;

    private Accommodation $hall;

    protected function setUp(): void
    {
        parent::setUp();
        // Meio do mês, para os 30 dias caberem em dois meses de um jeito previsível.
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'America/Sao_Paulo'));

        $this->property = Property::factory()->create(['slug' => 'pousada']);
        $this->chale = Accommodation::factory()->for($this->property)->create(['name' => 'Chalé']);
        // Espaço e apartamento ficam no mesmo imóvel: contam como um só na ocupação.
        $this->espaco = Accommodation::factory()->for($this->property)->create(['name' => 'Espaço', 'shared_unit' => 'casa']);
        $this->apto = Accommodation::factory()->for($this->property)->create(['name' => 'Apto', 'shared_unit' => 'casa']);
        $this->hall = Accommodation::factory()->for($this->property)->create(['name' => 'Salão', 'booking_mode' => 'hourly', 'hourly_price' => 10000, 'shared_unit' => 'casa']);
        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
    }

    private function booking(Accommodation $acc, string $in, string $out, int $total, BookingStatus $status = BookingStatus::Confirmed, array $extra = []): Booking
    {
        $booking = $this->property->bookings()->create([
            'status' => $status, 'check_in' => $in, 'check_out' => $out, 'guests' => 2, 'guest_name' => 'Ana',
            'guest_phone' => '11999990000', 'total' => $total,
            'confirmed_at' => in_array($status, [BookingStatus::Confirmed, BookingStatus::Cancelled], true) ? now() : null,
            'cancelled_at' => $status === BookingStatus::Cancelled ? now() : null,
        ] + $extra);
        $booking->accommodations()->attach($acc->id, ['subtotal' => $total]);

        return $booking;
    }

    public function test_revenue_and_occupancy_count_each_night_where_it_happens(): void
    {
        // 3 noites a R$ 300: 30/09 fica em setembro, 01 e 02/10 em outubro.
        $this->booking($this->chale, '2026-09-30', '2026-10-03', 90000, extra: ['arrival_time' => '16:30']);
        // Espaço e apartamento em noites diferentes do mesmo imóvel.
        $this->booking($this->espaco, '2026-10-10', '2026-10-12', 100000, extra: ['arrival_time' => '16:00']);
        $this->booking($this->apto, '2026-10-12', '2026-10-13', 30000);
        // Festa de 4 horas.
        $this->booking($this->hall, '2026-10-15', '2026-10-15', 40000, extra: ['start_time' => '14:00', 'end_time' => '18:00']);
        // Não entram: recusada e cancelada.
        $this->booking($this->chale, '2026-10-05', '2026-10-07', 50000, BookingStatus::Declined);
        $this->booking($this->chale, '2026-10-08', '2026-10-09', 50000, BookingStatus::Cancelled);

        $stats = $this->getJson('/api/admin/stats?period=month')->assertOk()->json();

        $this->assertSame(['2026-10-01', '2026-10-31', 31], [$stats['period']['from'], $stats['period']['to'], $stats['period']['days']]);
        $k = $stats['kpis'];
        // 2 noites do chalé (60000) + Espaço 100000 + Apto 30000 + festa 40000.
        $this->assertSame(230000, $k['revenue']);
        $this->assertSame(5, $k['nights_sold']);
        // 5 noites vendidas em 2 imóveis × 31 noites.
        $this->assertSame(round(5 / 62 * 100, 1), $k['occupancy']);
        $this->assertSame((int) round(190000 / 5), $k['adr']);
        $this->assertSame([3, 1, 4, 1], [$k['bookings'], $k['events'], $k['hours_sold'], $k['cancellations']]);

        $months = collect($stats['monthly'])->keyBy('month');
        $this->assertCount(12, $stats['monthly']);
        $this->assertSame(30000, $months['2026-09']['revenue']);
        $this->assertSame(230000, $months['2026-10']['revenue']);

        $byAcc = collect($stats['by_accommodation'])->keyBy('name');
        $this->assertSame([100000, 2], [$byAcc['Espaço']['revenue'], $byAcc['Espaço']['nights']]);
        $this->assertSame([40000, 4, true], [$byAcc['Salão']['revenue'], $byAcc['Salão']['hours'], $byAcc['Salão']['hourly']]);

        $this->assertSame([['hour' => 16, 'count' => 1]], $stats['arrivals']); // o chalé chegou em setembro
        $this->assertSame(1, $stats['weekdays'][CarbonImmutable::parse('2026-10-10')->dayOfWeek]);
    }

    public function test_previous_period_and_request_conversion(): void
    {
        $this->booking($this->chale, '2026-10-10', '2026-10-12', 60000);
        $this->booking($this->chale, '2026-09-10', '2026-09-11', 30000);
        // Um recusado e um que venceu sem resposta.
        $this->booking($this->chale, '2026-10-02', '2026-10-03', 30000, BookingStatus::Declined);
        $this->booking($this->chale, '2026-10-04', '2026-10-05', 30000, BookingStatus::Pending);

        $stats = $this->getJson('/api/admin/stats?period=30d')->assertOk()->json();

        $this->assertSame(60000, $stats['kpis']['revenue']);
        $this->assertSame(30000, $stats['previous']['revenue']);
        // A reserva mais antiga (10/09) é depois do começo do período anterior (21/08): não compara.
        $this->assertFalse($stats['has_previous']);
        // Pedidos feitos agora: 2 aceitos, 1 recusado e 1 vencido sem resposta = 50%.
        $this->assertSame(50, $stats['kpis']['conversion']);
    }

    public function test_period_is_validated_and_needs_login(): void
    {
        $this->getJson('/api/admin/stats?period=5y')->assertJsonValidationErrors('period');
        $this->assertSame('2025-11-01', $this->getJson('/api/admin/stats?period=12m')->json('period.from'));
    }
}
