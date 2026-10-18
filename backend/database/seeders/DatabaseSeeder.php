<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Pousada inicial (tenant #1): Espaço Bela Vista, em Mairiporã/SP.
 * Textos e preços vêm de ivoforti.com. A capacidade foi estimada pelas camas,
 * porque o site não informa; as datas de feriado são de exemplo.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::updateOrCreate(['slug' => 'espaco-bela-vista'], [
            'name' => 'Espaço Bela Vista',
            'tagline' => 'Piscina, fogueira e vista para as montanhas em Mairiporã',
            'description' => 'Um espaço com piscina e salão de festas para a família e um chalé de 98 m² com hidromassagem para o casal, em Mairiporã. Reserve direto com a gente, sem taxa de aplicativo.',
            'highlights' => [
                ['title' => 'Para a família', 'text' => 'Piscina com escadaria larga para as crianças, fogueira e salão com sinuca e churrasqueira.'],
                ['title' => 'Para o casal', 'text' => 'Chalé com cama king, banheira de hidromassagem e vista para as montanhas.'],
                ['title' => 'Reserva direta', 'text' => 'Você fala com a gente pelo WhatsApp, sem intermediário e sem taxa de aplicativo.'],
            ],
            'story' => [
                [
                    'time' => '8h', 'period' => 'Manhã', 'title' => 'Acordar sem pressa',
                    'text' => 'Vista para as montanhas no chalé, pátio de palmeiras imperiais no espaço e café da manhã opcional.',
                    'couple' => 'Acordar na cama king da suíte master, com vista para as montanhas. O café da manhã é opcional.',
                    'family' => 'As crianças acordam com o pátio de palmeiras imperiais lá fora. O café da manhã é opcional.',
                ],
                [
                    'time' => '15h', 'period' => 'Tarde', 'title' => 'A tarde inteira pela frente',
                    'text' => 'Piscina com escadaria larga, churrasqueira no salão de festas ou um livro na varanda do chalé.',
                    'couple' => 'Um livro da sala do chalé e a varanda com cadeiras para ver o dia passar.',
                    'family' => 'Piscina com escadaria larga para os pequenos entrarem com calma e churrasqueira pronta no salão de festas.',
                ],
                [
                    'time' => '21h', 'period' => 'Noite', 'title' => 'Quando o deck acende',
                    'text' => 'Fogueira, deck com LED, sinuca no salão ou a banheira de hidromassagem do chalé.',
                    'couple' => 'Jantar na cozinha do chalé e, para fechar, a banheira de hidromassagem extra grande.',
                    'family' => 'Roda de fogueira, deck iluminado e sinuca no salão para quem não quer dormir cedo.',
                ],
            ],
            'email' => 'contato@ivoforti.com',
            'phone' => '1146043867',
            'whatsapp' => '5511950211636',
            'address' => 'R. Custódio, 180',
            'city' => 'Mairiporã',
            'state' => 'SP',
        ]);

        $property->accommodations()->updateOrCreate(['slug' => 'espaco-bela-vista'], [
            'name' => 'Espaço Bela Vista',
            'description' => 'Apartamento de 30 m² com sala (sofá e TV 32" com Fire TV e streamings), quarto de casal, quarto com dois beliches e banheiro. Do lado de fora, pátio com palmeiras imperiais, deck com LED à noite, piscina com escadaria larga para as crianças, área de fogueira e salão de festas com cozinha, churrasqueira e sinuca. Valores para o casal; pessoas a mais têm acréscimo.',
            'amenities' => ['Piscina familiar com escadaria larga', 'Salão de festas com cozinha e churrasqueira', 'Mesa de sinuca', 'Área de fogueira', 'Deck com iluminação de LED', 'TV 32" com Fire TV e streamings', 'Rampa de acesso', 'Café da manhã opcional'],
            'max_guests' => 6,
            'base_price' => 49700,
            'weekend_price' => 99700,
            'min_nights' => 1,
            'sort_order' => 1,
        ]);

        $chale = $property->accommodations()->updateOrCreate(['slug' => 'chale-master'], [
            'name' => 'Chalé Master',
            'description' => 'Chalé de 98 m² com vista para as montanhas. Suíte master com cama king, closet e TV com streamings; banheiro em porcelanato com banheira de hidromassagem extra grande para o casal. Sala com livros, cozinha completa compacta, sala de jantar, segundo quarto com duas camas de solteiro e varanda com cadeiras. Acesso por escada e ponte de madeira, perto do centro e dos espaços de casamento.',
            'amenities' => ['Vista para as montanhas', 'Hidromassagem para casal', 'Cama king com closet', 'Cozinha completa', 'TV com streamings', 'Varanda', 'Perto do centro'],
            'max_guests' => 4,
            'base_price' => 49700,
            'weekend_price' => 99700,
            'min_nights' => 1,
            'sort_order' => 2,
        ]);

        $chale->seasonalPrices()->updateOrCreate(['name' => 'Feriado (réveillon)'], [
            'starts_on' => now()->year.'-12-26',
            'ends_on' => (now()->year + 1).'-01-02',
            'price' => 129700,
            'weekend_price' => null,
        ]);

        User::updateOrCreate(['email' => 'dono@exemplo.com.br'], [
            'property_id' => $property->id,
            'name' => 'Dono da Pousada',
            'password' => env('SEED_OWNER_PASSWORD', 'password'),
        ]);
    }
}
