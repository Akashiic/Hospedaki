<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use App\Services\DomainService;
use App\Support\Dns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Redes sociais, palavras-chave e metas de SEO, layout das fotos, temas e domínio próprio.
 */
class SiteExtrasTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->property = Property::factory()->create(['slug' => 'pousada']);
        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
    }

    /**
     * Respostas de DNS prontas. Um objeto só, que o teste vai mudando: o controlador (com o
     * serviço e o Dns dentro) fica guardado na rota entre uma requisição e outra.
     */
    private function fakeDns(array $a = [], array $cname = []): void
    {
        $this->dns ??= new class extends Dns
        {
            public array $records = [];

            public array $cnames = [];

            public function a(string $host): array
            {
                return $this->records[$host] ?? [];
            }

            public function cname(string $host): array
            {
                return $this->cnames[$host] ?? [];
            }
        };
        $this->dns->records = $a;
        $this->dns->cnames = $cname;
        $this->app->instance(Dns::class, $this->dns);
    }

    private ?Dns $dns = null;

    public function test_social_links_accept_a_handle_or_the_profile_link(): void
    {
        $this->putJson('/api/admin/site/social', ['instagram' => 'evil.com/x', 'google' => 'maps.evil.com'])
            ->assertJsonValidationErrors(['instagram', 'google']);

        $this->putJson('/api/admin/site/social', [
            'instagram' => '@pousada.do.sol',
            'facebook' => 'facebook.com/pousadadosol',
            'tripadvisor' => 'https://www.tripadvisor.com.br/Hotel_Review-g1-d2',
            'google' => 'https://maps.app.goo.gl/abc123',
            'youtube' => '',
        ])->assertOk()->assertJsonPath('social', [
            'instagram' => 'https://www.instagram.com/pousada.do.sol/',
            'facebook' => 'https://facebook.com/pousadadosol',
            'tripadvisor' => 'https://www.tripadvisor.com.br/Hotel_Review-g1-d2',
            'google' => 'https://maps.app.goo.gl/abc123',
        ]);

        // O site recebe na ordem do rodapé.
        $this->assertSame(['instagram', 'facebook', 'tripadvisor', 'google'], array_keys($this->getJson('/api/properties/pousada')->json('data.social')));

        $this->putJson('/api/admin/site/social', [])->assertOk()->assertJsonPath('social', []);
    }

    public function test_keywords_author_and_coordinates(): void
    {
        $this->putJson('/api/admin/site/seo', ['indexable' => true, 'latitude' => -23.3])->assertJsonValidationErrors('longitude');

        $this->putJson('/api/admin/site/seo', [
            'indexable' => true,
            'keywords' => ' pousada em Mairiporã,chalé com hidromassagem , , Pousada em Mairiporã, piscina',
            'author' => 'Pousada do Sol',
            'latitude' => -23.318,
            'longitude' => -46.587,
        ])->assertOk()
            ->assertJsonPath('seo.keywords', 'pousada em Mairiporã, chalé com hidromassagem, piscina')
            ->assertJsonPath('seo.author', 'Pousada do Sol')
            ->assertJsonPath('seo.latitude', -23.318);

        $this->putJson('/api/admin/site/pages/lugar', ['keywords' => 'fogueira, trilha'])->assertOk()->assertJsonPath('pages.lugar.keywords', 'fogueira, trilha');
    }

    public function test_photo_layout_of_the_accommodation_pages(): void
    {
        $this->getJson('/api/admin/site')->assertJsonPath('pages.acomodacoes.photos', 'grid');
        $this->putJson('/api/admin/site/pages/acomodacoes', ['photos' => 'slideshow'])->assertJsonValidationErrors('photos');
        $this->putJson('/api/admin/site/pages/acomodacoes', ['photos' => 'carousel'])->assertOk();
        $this->getJson('/api/properties/pousada')->assertJsonPath('data.pages.acomodacoes.photos', 'carousel');
        $this->assertArrayNotHasKey('photos', $this->getJson('/api/admin/site')->json('pages.lugar'));
    }

    public function test_owner_asks_to_hear_about_themes(): void
    {
        $this->getJson('/api/admin/site')->assertJsonPath('themes_waitlist', false);
        $this->putJson('/api/admin/site/themes/notify', ['note' => 'Tema praia'])->assertOk()->assertJsonPath('themes_waitlist', true);
    }

    public function test_domain_by_dns_without_the_vercel_integration(): void
    {
        $this->fakeDns();

        $this->putJson('/api/admin/site/domain', ['domain' => 'https://pousada'])->assertJsonValidationErrors('domain');
        $this->putJson('/api/admin/site/domain', ['domain' => 'meu-site.vercel.app'])->assertJsonValidationErrors('domain');

        $this->putJson('/api/admin/site/domain', ['domain' => 'https://www.PousadaDoSol.com.br/inicio'])->assertOk()
            ->assertJsonPath('domain', 'pousadadosol.com.br')
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('automatic', false)
            ->assertJsonPath('records', [
                ['type' => 'A', 'name' => '@', 'value' => DomainService::A_RECORD],
                ['type' => 'CNAME', 'name' => 'www', 'value' => DomainService::CNAME_TARGET],
            ]);
        $this->assertNull($this->getJson('/api/properties/pousada')->json('data.domain'));

        // Apontando para a hospedagem antiga: configurado errado.
        $this->fakeDns(['pousadadosol.com.br' => ['1.2.3.4']]);
        $this->postJson('/api/admin/site/domain/check')->assertJsonPath('status', 'misconfigured');

        $this->fakeDns(['pousadadosol.com.br' => [DomainService::A_RECORD]]);
        $this->postJson('/api/admin/site/domain/check')->assertJsonPath('status', 'active');
        $this->getJson('/api/properties/pousada')->assertJsonPath('data.domain', 'pousadadosol.com.br');

        // O site no domínio novo pode chamar a API pelo navegador.
        $this->getJson('/api/properties/pousada', ['Origin' => 'https://www.pousadadosol.com.br'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://www.pousadadosol.com.br');
        $this->getJson('/api/properties/pousada', ['Origin' => 'https://outro.com.br'])->assertHeaderMissing('Access-Control-Allow-Origin');

        $this->deleteJson('/api/admin/site/domain')->assertOk()->assertJsonPath('domain', null);
    }

    public function test_subdomain_uses_a_cname(): void
    {
        $this->fakeDns(cname: ['reservas.pousada.com.br' => ['cname.vercel-dns.com']]);
        $this->putJson('/api/admin/site/domain', ['domain' => 'reservas.pousada.com.br'])->assertOk()
            ->assertJsonPath('records', [['type' => 'CNAME', 'name' => 'reservas', 'value' => DomainService::CNAME_TARGET]])
            ->assertJsonPath('status', 'active');
    }

    public function test_domain_is_added_to_vercel_when_the_integration_is_set(): void
    {
        config(['services.vercel' => ['token' => 'tok', 'project_id' => 'prj_1', 'team_id' => 'team_1']]);
        $this->fakeDns();
        Http::fake([
            'api.vercel.com/v10/projects/prj_1/domains?*' => Http::response(['name' => 'x', 'verified' => true]),
            'api.vercel.com/v10/projects/prj_1/domains/pousadadosol.com.br*' => Http::response(['verified' => true]),
            'api.vercel.com/v6/domains/pousadadosol.com.br/config*' => Http::response(['misconfigured' => false]),
        ]);

        $this->putJson('/api/admin/site/domain', ['domain' => 'pousadadosol.com.br'])->assertOk()
            ->assertJsonPath('automatic', true)
            ->assertJsonPath('status', 'active');

        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['name'] === 'www.pousadadosol.com.br' && $r['redirect'] === 'pousadadosol.com.br' && str_contains($r->url(), 'teamId=team_1'));
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['name'] === 'pousadadosol.com.br' && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_demo_cannot_attach_a_domain(): void
    {
        config(['demo.enabled' => true, 'demo.property_slug' => 'pousada']);
        $this->putJson('/api/admin/site/domain', ['domain' => 'pousadadosol.com.br'])->assertJsonValidationErrors('domain');
    }
}
