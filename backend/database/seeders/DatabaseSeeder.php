<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados de exemplo para desenvolvimento local.
 * Nomes, textos e preços são provisórios: troque pelos reais no painel.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::updateOrCreate(['slug' => 'pousada-exemplo'], [
            'name' => 'Pousada Exemplo',
            'description' => 'Um chalé aconchegante e um espaço amplo, cercados de natureza.',
            'email' => 'contato@exemplo.com.br',
            'whatsapp' => '5500000000000',
            'city' => 'Cidade',
            'state' => 'SP',
        ]);

        $chale = $property->accommodations()->updateOrCreate(['slug' => 'chale'], [
            'name' => 'Chalé',
            'description' => 'Chalé para casal com cama queen, cozinha e varanda.',
            'max_guests' => 2,
            'base_price' => 35000,
            'weekend_price' => 45000,
            'min_nights' => 1,
            'sort_order' => 1,
        ]);

        $espaco = $property->accommodations()->updateOrCreate(['slug' => 'espaco'], [
            'name' => 'Espaço',
            'description' => 'Espaço amplo para famílias e grupos.',
            'max_guests' => 6,
            'base_price' => 50000,
            'weekend_price' => 65000,
            'min_nights' => 1,
            'sort_order' => 2,
        ]);

        foreach ([$chale, $espaco] as $accommodation) {
            $accommodation->seasonalPrices()->updateOrCreate(['name' => 'Alta temporada (réveillon)'], [
                'starts_on' => now()->year.'-12-26',
                'ends_on' => (now()->year + 1).'-01-02',
                'price' => (int) round($accommodation->base_price * 1.5),
                'weekend_price' => null,
            ]);
        }

        User::updateOrCreate(['email' => 'dono@exemplo.com.br'], [
            'property_id' => $property->id,
            'name' => 'Dono da Pousada',
            'password' => env('SEED_OWNER_PASSWORD', 'password'),
        ]);
    }
}
