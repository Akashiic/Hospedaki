<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Services\DemoService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
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

        // Os exemplos do dia a dia (o histórico de um ano fica antes deles).
        $bookings = array_values(array_filter($this->withToken($token)->getJson('/api/admin/bookings')->assertOk()->json('data'), fn ($b) => $b['guest_phone'] === '11999990000'));
        $this->assertCount(7, $bookings);
        $this->assertSame(['cancelled', 'confirmed', 'confirmed', 'declined', 'confirmed', 'pending', 'pending'], array_column($bookings, 'status'));
        // Um exemplo em cada filtro da aba Reservas.
        $counts = $this->withToken($token)->getJson('/api/admin/bookings?scope=all')->json('meta.counts');
        // (Concluídas, passadas e canceladas também somam o histórico de um ano.)
        $this->assertSame(['requests' => 2, 'upcoming' => 1, 'ongoing' => 1], array_intersect_key($counts, array_flip(['requests', 'upcoming', 'ongoing'])));
        $this->assertGreaterThan(7, $counts['all']);
        $this->assertTrue(collect($bookings)->every(fn ($booking) => $booking['total'] > 0));
        $this->assertCount(2, $this->withToken($token)->getJson('/api/admin/blocked-dates')->json('data'));

        // Os exemplos não avisam o dono por e-mail.
        Notification::assertNothingSent();
    }

    public function test_site_shows_the_demo_flag_and_resets_once_a_day(): void
    {
        $this->getJson('/api/properties/pousada-demo')->assertOk()->assertJsonPath('data.demo', true);
        $this->assertSame(7, $this->samples()->count());

        // Um visitante mexe na demo: recusa tudo e apaga os bloqueios.
        Booking::query()->update(['status' => BookingStatus::Declined]);
        BlockedDate::query()->delete();

        // No mesmo dia, a demo fica como o visitante deixou.
        $this->getJson('/api/properties/pousada-demo')->assertOk();
        $this->assertSame(0, Booking::where('status', BookingStatus::Pending)->count());

        // E esconde seções da página inicial.
        $this->property->update(['settings' => ['home_sections' => [['key' => 'stays', 'visible' => false]]]]);

        // No dia seguinte (no fuso da pousada), volta ao estado inicial.
        $this->travelTo(CarbonImmutable::now($this->property->timezone)->addDay()->setTime(0, 30));
        $this->getJson('/api/properties/pousada-demo')->assertOk();
        $this->assertSame(2, Booking::where('status', BookingStatus::Pending)->count());
        $this->assertSame(2, BlockedDate::count());
        $this->assertNull($this->property->fresh()->settings);
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
        $this->assertSame(7, $this->samples()->count());

        config(['demo.enabled' => false]);
        $this->artisan('demo:reset')->assertFailed();
    }

    public function test_reset_restores_the_original_accommodations(): void
    {
        $this->seed();
        config(['demo.property_slug' => DatabaseSeeder::SLUG]);
        $property = Property::where('slug', DatabaseSeeder::SLUG)->firstOrFail();
        $demo = app(DemoService::class);
        $demo->reset($property);

        // Um visitante renomeia o chalé, oculta a suíte e exclui o apartamento.
        $property->accommodations()->where('slug', 'chale-master')->update(['name' => 'Bagunça', 'base_price' => 1]);
        $property->accommodations()->where('slug', 'suite-vista-do-vale')->update(['active' => false]);
        // (Com reservas no histórico o painel não deixa excluir; aqui o teste solta as reservas antes.)
        $apto = $property->accommodations()->where('slug', 'apto-bela-piscina')->first();
        DB::table('booking_accommodation')->where('accommodation_id', $apto->id)->delete();
        $apto->delete();

        $demo->reset($property);

        $accommodations = $property->accommodations()->get();
        $this->assertSame(['Chalé Master', 'Espaço Bela Vista', 'Apto Bela Piscina', 'Suíte Vista do Vale', 'Salão de Festas'], $accommodations->pluck('name')->all());
        $this->assertTrue($accommodations->every->active);
        $this->assertSame(49700, $accommodations->first()->base_price);
        // Sete estadias de exemplo e uma festa no salão, reservado por hora.
        $this->assertSame(8, $property->bookings()->where('guest_phone', '11999990000')->count());
        $this->assertSame(1, $property->bookings()->whereNotNull('start_time')->where('guest_phone', '11999990000')->count());
    }

    public function test_reset_restores_the_site_identity_pages_and_seo(): void
    {
        $this->seed();
        config(['demo.property_slug' => DatabaseSeeder::SLUG]);
        $property = Property::where('slug', DatabaseSeeder::SLUG)->firstOrFail();
        $demo = app(DemoService::class);
        $demo->reset($property);

        // Um visitante troca o nome e a cor, tira o contato do menu e esconde o site do Google.
        $property->update(['name' => 'Bagunça', 'primary_color' => '#ff0000', 'whatsapp' => null, 'settings' => [
            'pages' => ['contato' => ['menu' => false]],
            'seo' => ['indexable' => false],
        ]]);

        $demo->reset($property);
        $property->refresh();

        $this->assertSame(['Espaço Bela Vista', '#2f6f4f', '5511950211636'], [$property->name, $property->primary_color, $property->whatsapp]);
        $this->assertNull($property->settings);
    }

    public function test_demo_blocks_site_image_uploads(): void
    {
        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));

        $this->postJson('/api/admin/site/images/logo', ['photo' => UploadedFile::fake()->image('logo.png', 400, 100)])
            ->assertJsonValidationErrors('photo');
        $this->assertNull($this->property->fresh()->logo_path);
    }

    public function test_daily_reset_keeps_the_visitor_who_just_logged_in(): void
    {
        $old = $this->postJson('/api/admin/demo-login')->assertOk()->json('token');

        // Dia seguinte: o primeiro login dispara o reset, que roda depois da resposta.
        $this->travelTo(CarbonImmutable::now($this->property->timezone)->addDay()->setTime(0, 30));
        $token = $this->postJson('/api/admin/demo-login')->assertOk()->json('token');
        $this->app['auth']->forgetGuards();

        $this->assertCount(7, array_filter($this->withToken($token)->getJson('/api/admin/bookings')->assertOk()->json('data'), fn ($b) => $b['guest_phone'] === '11999990000'));
        // A sessão de ontem foi encerrada pelo reset.
        $this->app['auth']->forgetGuards();
        $this->withToken($old)->getJson('/api/admin/me')->assertUnauthorized();
    }

    /** Os exemplos do dia a dia (o histórico de um ano usa outro telefone). */
    private function samples()
    {
        return Booking::where('property_id', $this->property->id)->where('guest_phone', '11999990000');
    }

    public function test_reset_adds_a_year_of_history_without_overlaps(): void
    {
        app(DemoService::class)->reset($this->property);

        $all = Booking::where('property_id', $this->property->id)->with('accommodations')->get();
        $history = $all->where('guest_phone', '11999990001');
        $this->assertGreaterThan(30, $history->count());
        $this->assertTrue($history->every(fn ($b) => $b->total > 0 && $b->accommodations->count() === 1));
        $this->assertGreaterThan(CarbonImmutable::now()->subMonths(13)->toDateString(), $history->min('check_in')->toDateString());

        // Nenhuma noite vendida duas vezes na mesma acomodação, nem por cima dos exemplos do dia a dia.
        $this->assertTrue($history->every(fn ($b) => $b->check_out->lte($this->property->today())));
        $nights = [];
        foreach ($all->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Pending]) as $booking) {
            for ($d = $booking->check_in; $d->lt($booking->check_out); $d = $d->addDay()) {
                $key = $booking->accommodations->first()->id.'|'.$d->toDateString();
                $this->assertArrayNotHasKey($key, $nights);
                $nights[$key] = true;
            }
        }

        // O mesmo sorteio a cada dia: os números das estatísticas não pulam.
        $total = $history->sum('total');
        app(DemoService::class)->reset($this->property);
        $this->assertSame($total, Booking::where('property_id', $this->property->id)->where('guest_phone', '11999990001')->sum('total'));
    }
}
