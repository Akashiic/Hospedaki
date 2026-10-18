<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_the_seeder_again_keeps_the_owner_password(): void
    {
        $this->seed();
        $owner = User::where('email', 'dono@exemplo.com.br')->firstOrFail();
        $this->assertNotNull($owner->property_id);

        $owner->update(['password' => 'senha-nova-do-dono']);
        $this->seed();

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('senha-nova-do-dono', $owner->fresh()->password));
    }

    public function test_production_seed_requires_an_owner_password(): void
    {
        if (env('SEED_OWNER_PASSWORD')) {
            $this->markTestSkipped('SEED_OWNER_PASSWORD está definida neste ambiente.');
        }

        $this->app['env'] = 'production';

        // Chama o seeder direto: o db:seed pede confirmação em produção.
        $this->expectExceptionMessage('SEED_OWNER_PASSWORD');
        $this->app->make(DatabaseSeeder::class)->run();
    }
}
