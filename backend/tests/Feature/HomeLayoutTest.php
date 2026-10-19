<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Property;
use App\Models\User;
use App\Support\HomeLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::factory()->create(['slug' => 'pousada']);
        Accommodation::factory()->for($this->property)->create();
        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
    }

    private function keys(array $sections, bool $onlyVisible = false): array
    {
        return array_column(array_filter($sections, fn ($s) => ! $onlyVisible || $s['visible']), 'key');
    }

    public function test_default_layout_is_the_classic_home(): void
    {
        $sections = $this->getJson('/api/properties/pousada')->assertOk()->json('data.home_sections');

        $this->assertSame(HomeLayout::SECTIONS, $this->keys($sections));
        $this->assertSame(['hero', 'story', 'stays'], $this->keys($sections, true));
    }

    public function test_owner_reorders_and_hides_sections(): void
    {
        $this->putJson('/api/admin/layout', ['sections' => [
            ['key' => 'hero', 'visible' => true],
            ['key' => 'stays', 'visible' => true],
            ['key' => 'gallery', 'visible' => true],
            ['key' => 'story', 'visible' => false],
        ]])->assertOk();

        $sections = $this->getJson('/api/properties/pousada')->json('data.home_sections');
        $this->assertSame(['hero', 'stays', 'gallery'], $this->keys($sections, true));
        // As seções que ficaram de fora da lista entram no fim, escondidas.
        $this->assertSame(['hero', 'stays', 'gallery', 'story', 'intro', 'extras'], $this->keys($sections));
        $this->assertSame($sections, $this->getJson('/api/admin/layout')->json('sections'));
    }

    public function test_the_cover_always_stays_first_and_visible(): void
    {
        $sections = $this->putJson('/api/admin/layout', ['sections' => [
            ['key' => 'stays', 'visible' => true],
            ['key' => 'hero', 'visible' => false],
        ]])->assertOk()->json('sections');

        $this->assertSame(['key' => 'hero', 'visible' => true], $sections[0]);
        $this->assertSame('stays', $sections[1]['key']);
    }

    public function test_unknown_or_repeated_sections_are_rejected(): void
    {
        $this->putJson('/api/admin/layout', ['sections' => [['key' => 'banner', 'visible' => true]]])
            ->assertUnprocessable()->assertJsonValidationErrors('sections.0.key');

        $this->putJson('/api/admin/layout', ['sections' => [
            ['key' => 'stays', 'visible' => true],
            ['key' => 'stays', 'visible' => false],
        ]])->assertUnprocessable();
    }

    public function test_saving_keeps_other_settings(): void
    {
        $this->property->update(['settings' => ['outra' => 'coisa']]);

        $this->putJson('/api/admin/layout', ['sections' => [['key' => 'stays', 'visible' => true]]])->assertOk();

        $this->assertSame('coisa', $this->property->fresh()->settings['outra']);
    }

    public function test_layout_needs_login(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', '')->getJson('/api/admin/layout')->assertUnauthorized();
    }
}
