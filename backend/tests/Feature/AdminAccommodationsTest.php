<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\Media;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAccommodationsTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::factory()->create(['slug' => 'pousada']);
        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Chalé do Lago',
            'description' => 'Chalé novo com deck sobre o lago.',
            'amenities' => ['Deck', ' Lareira ', ''],
            'max_guests' => 4,
            'included_guests' => 2,
            'base_price' => 45000,
            'weekend_price' => 60000,
            'extra_guest_price' => 8000,
            'cleaning_fee' => 12000,
            'weekly_discount' => 10,
            'monthly_discount' => 20,
            'min_nights' => 2,
            'active' => true,
            'best_for' => null,
        ];
    }

    public function test_owner_creates_an_accommodation_that_shows_on_the_site(): void
    {
        $created = $this->postJson('/api/admin/accommodations', $this->payload())->assertCreated()->json('data');

        $this->assertSame('chale-do-lago', $created['slug']);
        $this->assertSame(['Deck', 'Lareira'], $created['amenities']);
        $this->assertSame(12000, $created['cleaning_fee']);

        $site = $this->getJson('/api/properties/pousada')->json('data.accommodations');
        $this->assertSame(['Chalé do Lago'], array_column($site, 'name'));

        // Mesmo nome de novo: endereço diferente.
        $this->assertSame('chale-do-lago-2', $this->postJson('/api/admin/accommodations', $this->payload())->json('data.slug'));
    }

    public function test_validation_speaks_portuguese(): void
    {
        $this->postJson('/api/admin/accommodations', $this->payload(['name' => '', 'included_guests' => 9, 'weekly_discount' => 95]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'included_guests', 'weekly_discount'])
            ->assertJsonPath('errors.name.0', 'O campo nome é obrigatório.');
    }

    public function test_hiding_removes_it_from_the_site_and_bookings(): void
    {
        $id = $this->postJson('/api/admin/accommodations', $this->payload())->json('data.id');

        $this->putJson("/api/admin/accommodations/{$id}", $this->payload(['active' => false, 'name' => 'Chalé do Lago (reforma)']))
            ->assertOk()->assertJsonPath('data.active', false);

        $this->assertSame([], $this->getJson('/api/properties/pousada')->json('data.accommodations'));
        $this->assertCount(1, $this->getJson('/api/admin/accommodations')->json('data'));
    }

    public function test_choosing_an_accommodation_for_couples_unsets_the_previous_one(): void
    {
        $a = $this->postJson('/api/admin/accommodations', $this->payload(['best_for' => 'casal']))->json('data.id');
        $b = $this->postJson('/api/admin/accommodations', $this->payload(['name' => 'Suíte', 'best_for' => 'casal']))->json('data.id');

        $this->assertNull(Accommodation::find($a)->best_for);
        $this->assertSame('casal', Accommodation::find($b)->best_for);
    }

    public function test_delete_only_without_booking_history(): void
    {
        $free = Accommodation::factory()->for($this->property)->create();
        $booked = Accommodation::factory()->for($this->property)->create();
        $booking = $this->property->bookings()->create([
            'status' => BookingStatus::Declined, 'check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(4)->toDateString(),
            'guests' => 2, 'guest_name' => 'Maria', 'guest_phone' => '11999999999', 'total' => 1000,
        ]);
        $booking->accommodations()->attach($booked->id, ['subtotal' => 1000]);

        $this->deleteJson("/api/admin/accommodations/{$booked->id}")->assertUnprocessable()->assertJsonValidationErrors('accommodation');
        $this->deleteJson("/api/admin/accommodations/{$free->id}")->assertNoContent();
        $this->assertModelMissing($free);
    }

    public function test_reorder(): void
    {
        $a = Accommodation::factory()->for($this->property)->create(['sort_order' => 1]);
        $b = Accommodation::factory()->for($this->property)->create(['sort_order' => 2]);

        $this->putJson('/api/admin/accommodations/order', ['ids' => [$b->id, $a->id]])->assertNoContent();

        $this->assertSame([$b->id, $a->id], array_column($this->getJson('/api/admin/accommodations')->json('data'), 'id'));
        $this->putJson('/api/admin/accommodations/order', ['ids' => [$a->id]])->assertUnprocessable();
    }

    public function test_photos_are_resized_stored_in_the_database_and_served(): void
    {
        $acc = Accommodation::factory()->for($this->property)->create();

        $photos = $this->post("/api/admin/accommodations/{$acc->id}/photos", [
            'photo' => UploadedFile::fake()->image('varanda.jpg', 3200, 2400),
            'caption' => 'Varanda',
        ], ['Accept' => 'application/json'])->assertOk()->json('data.photos');

        $media = Media::sole();
        $this->assertSame([1600, 1200], [$media->width, $media->height]);
        $this->assertStringEndsWith("/api/media/{$media->id}", $photos[0]['url']);

        $this->get("/api/media/{$media->id}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // Outra foto, depois troca a ordem e a legenda.
        $second = $this->post("/api/admin/accommodations/{$acc->id}/photos", ['photo' => UploadedFile::fake()->image('b.png', 800, 600)], ['Accept' => 'application/json'])
            ->json('data.photos.1.id');
        $this->putJson("/api/admin/accommodations/{$acc->id}/photos", ['photos' => [
            ['id' => $second, 'caption' => 'Capa nova'], ['id' => $photos[0]['id'], 'caption' => 'Varanda'],
        ]])->assertOk()->assertJsonPath('data.photos.0.caption', 'Capa nova');

        // Excluir a foto apaga a imagem guardada.
        $this->deleteJson("/api/admin/accommodations/{$acc->id}/photos/{$photos[0]['id']}")->assertOk()->assertJsonCount(1, 'data.photos');
        $this->assertModelMissing($media);
    }

    public function test_files_that_are_not_images_are_rejected(): void
    {
        $acc = Accommodation::factory()->for($this->property)->create();

        $this->post("/api/admin/accommodations/{$acc->id}/photos", ['photo' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_seasons_crud(): void
    {
        $acc = Accommodation::factory()->for($this->property)->create();
        $season = ['name' => 'Carnaval', 'starts_on' => '2027-02-06', 'ends_on' => '2027-02-10', 'price' => 90000, 'weekend_price' => null, 'min_nights' => 3];

        $id = $this->postJson("/api/admin/accommodations/{$acc->id}/seasons", $season)->assertOk()->json('data.seasons.0.id');
        $this->putJson("/api/admin/accommodations/{$acc->id}/seasons/{$id}", ['price' => 95000] + $season)->assertOk()->assertJsonPath('data.seasons.0.price', 95000);
        $this->postJson("/api/admin/accommodations/{$acc->id}/seasons", ['ends_on' => '2027-02-01'] + $season)->assertUnprocessable();
        $this->deleteJson("/api/admin/accommodations/{$acc->id}/seasons/{$id}")->assertOk()->assertJsonCount(0, 'data.seasons');
    }

    public function test_other_properties_accommodations_are_off_limits(): void
    {
        $other = Accommodation::factory()->for(Property::factory()->create())->create();

        $this->putJson("/api/admin/accommodations/{$other->id}", $this->payload())->assertNotFound();
        $this->deleteJson("/api/admin/accommodations/{$other->id}")->assertNotFound();
        $this->post("/api/admin/accommodations/{$other->id}/photos", ['photo' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_demo_blocks_photo_uploads(): void
    {
        config(['demo.enabled' => true, 'demo.property_slug' => 'pousada']);
        $acc = Accommodation::factory()->for($this->property)->create();

        $this->post("/api/admin/accommodations/{$acc->id}/photos", ['photo' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_seeding_again_keeps_what_the_owner_changed(): void
    {
        $this->seed();
        $property = Property::where('slug', DatabaseSeeder::SLUG)->firstOrFail();
        $chale = $property->accommodations()->where('slug', 'chale-master')->firstOrFail();
        $chale->update(['base_price' => 55500]);
        $chale->photos()->first()->delete();
        $property->accommodations()->where('slug', 'suite-vista-do-vale')->delete();

        $this->seed();

        $this->assertSame(55500, $chale->fresh()->base_price);
        $this->assertSame(10, $chale->photos()->count());
        // O que foi excluído volta só se estiver faltando no catálogo inicial (o seed cria o que falta).
        $this->assertSame(5, $property->accommodations()->count());
    }
}
