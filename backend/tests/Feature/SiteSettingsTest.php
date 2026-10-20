<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Media;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::factory()->create(['slug' => 'pousada', 'name' => 'Pousada Sol']);
        Accommodation::factory()->for($this->property)->create();
        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
    }

    private function image(int $width, int $height, string $name = 'foto.png'): UploadedFile
    {
        $gd = imagecreatetruecolor($width, $height);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 30, 120, 80));
        ob_start();
        imagepng($gd);
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, ob_get_clean());

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public function test_defaults_keep_every_page_in_the_menu_and_the_site_indexable(): void
    {
        $data = $this->getJson('/api/properties/pousada')->assertOk()->json('data');

        $this->assertSame(['inicio', 'lugar', 'acomodacoes', 'reservar', 'contato'], array_keys($data['pages']));
        $this->assertTrue(collect($data['pages'])->every(fn ($p) => $p['menu'] && $p['title'] === null && $p['seo_title'] === null));
        $this->assertTrue($data['seo']['indexable']);
        $this->assertNull($data['icon_url']);
        $this->assertNull($data['share_image_url']);
    }

    public function test_owner_edits_a_page_texts_seo_and_menu(): void
    {
        $this->putJson('/api/admin/site/pages/contato', [
            'menu' => false,
            'title' => '  Vamos conversar  ',
            'lead' => '',
            'seo_title' => 'Contato da Pousada Sol',
            'seo_description' => 'WhatsApp, telefone e endereço.',
        ])->assertOk()->assertJsonPath('pages.contato.title', 'Vamos conversar');

        $page = $this->getJson('/api/properties/pousada')->json('data.pages.contato');
        $this->assertFalse($page['menu']);
        $this->assertNull($page['lead']);
        $this->assertSame('Contato da Pousada Sol', $page['seo_title']);

        // Mudar outra página não mexe nesta, e só o que foi enviado muda.
        $this->putJson('/api/admin/site/pages/lugar', ['eyebrow' => 'Conheça'])->assertOk();
        $this->putJson('/api/admin/site/pages/contato', ['seo_title' => 'Fale conosco'])->assertOk()
            ->assertJsonPath('pages.contato.title', 'Vamos conversar')
            ->assertJsonPath('pages.contato.menu', false)
            ->assertJsonPath('pages.lugar.eyebrow', 'Conheça');
    }

    public function test_home_and_booking_pages_always_stay_in_the_menu(): void
    {
        $this->putJson('/api/admin/site/pages/reservar', ['menu' => false])->assertOk()->assertJsonPath('pages.reservar.menu', true);
        $this->putJson('/api/admin/site/pages/inicio', ['menu' => false])->assertOk()->assertJsonPath('pages.inicio.menu', true);
    }

    public function test_page_texts_are_validated(): void
    {
        $this->putJson('/api/admin/site/pages/contato', ['seo_title' => str_repeat('a', 71)])->assertJsonValidationErrors('seo_title');
        $this->putJson('/api/admin/site/pages/blog', ['title' => 'x'])->assertNotFound();
    }

    public function test_owner_updates_identity_and_contact(): void
    {
        $this->putJson('/api/admin/site/identity', [
            'name' => 'Pousada Lua',
            'tagline' => 'Na beira do rio',
            'primary_color' => '#AA3355',
            'whatsapp' => '5511988887777',
            'state' => 'mg',
        ])->assertOk()->assertJsonPath('identity.name', 'Pousada Lua');

        $this->assertDatabaseHas('properties', ['id' => $this->property->id, 'name' => 'Pousada Lua', 'primary_color' => '#aa3355', 'state' => 'MG']);

        $this->putJson('/api/admin/site/identity', ['name' => 'X', 'primary_color' => 'red', 'whatsapp' => '(11) 9999'])
            ->assertJsonValidationErrors(['primary_color', 'whatsapp']);
    }

    public function test_seo_settings_are_saved_and_validated(): void
    {
        $this->putJson('/api/admin/site/seo', [
            'description' => 'Pousada com piscina.',
            'indexable' => false,
            'google_verification' => 'abc_DEF-123',
            'ga_id' => 'G-ABC123XYZ',
            'meta_pixel_id' => '1234567890',
        ])->assertOk();

        $seo = $this->getJson('/api/properties/pousada')->json('data.seo');
        $this->assertFalse($seo['indexable']);
        $this->assertSame('G-ABC123XYZ', $seo['ga_id']);

        $this->putJson('/api/admin/site/seo', [
            'indexable' => true,
            'google_verification' => '<meta name="google-site-verification">',
            'ga_id' => 'UA-123',
            'meta_pixel_id' => 'abc',
        ])->assertJsonValidationErrors(['google_verification', 'ga_id', 'meta_pixel_id']);
    }

    public function test_logo_icon_and_share_image_are_resized_and_replaced(): void
    {
        $logo = $this->postJson('/api/admin/site/images/logo', ['photo' => $this->image(1600, 400)])->assertOk()->json('identity.logo_url');
        $this->assertStringContainsString('/api/media/', $logo);
        $media = Media::latest('id')->first();
        $this->assertSame(['image/png', 800, 200], [$media->mime, $media->width, $media->height]);

        $this->postJson('/api/admin/site/images/icon', ['photo' => $this->image(900, 600)])->assertOk();
        $icon = Media::latest('id')->first();
        $this->assertSame([512, 512], [$icon->width, $icon->height]);

        $this->postJson('/api/admin/site/images/share', ['photo' => $this->image(2000, 2000)])->assertOk();
        $share = Media::latest('id')->first();
        $this->assertSame(['image/jpeg', 1200, 630], [$share->mime, $share->width, $share->height]);

        $data = $this->getJson('/api/properties/pousada')->json('data');
        $this->assertStringEndsWith("/api/media/{$icon->id}", $data['icon_url']);
        $this->assertStringEndsWith("/api/media/{$share->id}", $data['share_image_url']);
        $this->assertStringEndsWith("/api/media/{$icon->id}", $this->getJson('/api/admin/me')->json('property.icon_url'));

        // Trocar o ícone apaga o anterior; remover o logo também.
        $this->postJson('/api/admin/site/images/icon', ['photo' => $this->image(300, 300)])->assertOk();
        $this->assertDatabaseMissing('media', ['id' => $icon->id]);
        $this->deleteJson('/api/admin/site/images/logo')->assertOk()->assertJsonPath('identity.logo_url', null);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        $this->assertNotNull($this->property->fresh()->settings['share_path'] ?? null);
    }

    public function test_unknown_image_kind_is_not_found(): void
    {
        $this->postJson('/api/admin/site/images/banner', ['photo' => $this->image(10, 10)])->assertNotFound();
    }
}
