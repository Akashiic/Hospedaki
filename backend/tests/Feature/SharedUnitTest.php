<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * O Espaço Bela Vista inclui o apartamento Bela, que também é alugado sozinho:
 * as duas acomodações dividem o imóvel (shared_unit) e uma ocupa a outra.
 */
class SharedUnitTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $espaco;

    private Accommodation $apto;

    private Accommodation $chale;

    protected function setUp(): void
    {
        parent::setUp();

        $property = Property::factory()->create(['slug' => 'pousada']);
        $this->espaco = Accommodation::factory()->for($property)->create(['shared_unit' => 'apto-bela', 'max_guests' => 6]);
        $this->apto = Accommodation::factory()->for($property)->create(['shared_unit' => 'apto-bela', 'max_guests' => 5]);
        $this->chale = Accommodation::factory()->for($property)->create(['max_guests' => 4]);
    }

    private function request(array $ids, int $in = 10, int $out = 12): TestResponse
    {
        return $this->postJson('/api/properties/pousada/booking-requests', [
            'accommodation_ids' => $ids,
            'check_in' => now()->addDays($in)->toDateString(),
            'check_out' => now()->addDays($out)->toDateString(),
            'guests' => 2,
            'guest_name' => 'Maria',
            'guest_phone' => '11999999999',
        ]);
    }

    public function test_a_booking_of_one_occupies_the_other(): void
    {
        $this->request([$this->espaco->id])->assertCreated();

        $this->request([$this->apto->id], 11, 13)->assertUnprocessable()->assertJsonValidationErrors('check_in');
        // Outras acomodações não são afetadas.
        $this->request([$this->chale->id])->assertCreated();
    }

    public function test_calendar_shows_the_other_unit_as_taken(): void
    {
        $this->apto->blockedDates()->create([
            'starts_on' => now()->addDays(5)->toDateString(),
            'ends_on' => now()->addDays(5)->toDateString(),
        ]);

        $nights = collect($this->getJson('/api/properties/pousada/availability?'.http_build_query([
            'from' => now()->toDateString(),
            'to' => now()->addDays(10)->toDateString(),
        ]))->json('accommodations'))->keyBy('id');

        $this->assertSame([now()->addDays(5)->toDateString()], $nights[$this->espaco->id]['unavailable']);
        $this->assertSame([], $nights[$this->chale->id]['unavailable']);
    }

    public function test_both_cannot_be_requested_together(): void
    {
        $this->request([$this->espaco->id, $this->apto->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accommodation_ids');
    }
}
