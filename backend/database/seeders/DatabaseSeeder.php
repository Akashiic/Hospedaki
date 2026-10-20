<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Models\Media;
use App\Models\Property;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Database\Seeder;

/**
 * Pousada inicial (tenant #1): Espaço Bela Vista, em Mairiporã/SP.
 * Textos, preços e fotos vêm de ivoforti.com (menu "Estadia & Eventos"). As fotos ficam no
 * próprio site (frontend/public/fotos) e entram aqui pelo caminho, como "/fotos/chale-1.jpg".
 *
 * Provisório: as diárias valem para 2 pessoas ("preços válidos para um casal") e a pousada
 * ainda não informou o acréscimo por pessoa a mais, então extra_guest_price fica nulo e o site
 * avisa que esse valor é combinado na confirmação. A capacidade foi estimada pelas camas, porque
 * o site deles não informa, e as datas de feriado são de exemplo.
 */
class DatabaseSeeder extends Seeder
{
    public const SLUG = 'espaco-bela-vista';

    public function run(): void
    {
        // Só cria o que falta: o seed roda a cada deploy e não pode desfazer o que o dono mudou no painel.
        $property = Property::firstOrCreate(['slug' => self::SLUG], $this->details());

        $this->photos($property, [
            ['/fotos/espaco-1.jpg', 'Piscina e salão de festas à noite', true],
            ['/fotos/lugar-1.jpg', 'Vista da pousada para a represa', true],
            ['/fotos/espaco-2.jpg', 'Piscina com vista para as montanhas', true],
            ['/fotos/chale-1.jpg', 'Varanda do Chalé Master com vista para as montanhas', true],
            ['/fotos/espaco-9.jpg', 'Deck iluminado à noite', true],
            ['/fotos/lugar-2.jpg', 'Pôr do sol visto da pousada', false],
            ['/fotos/espaco-7.jpg', 'Área da fogueira', false],
            ['/fotos/espaco-8.jpg', 'Pátio com palmeiras imperiais', false],
            ['/fotos/chale-2.jpg', 'Hidromassagem do Chalé Master', false],
        ]);

        $this->accommodations($property);

        // A senha só é definida quando o usuário é criado: rodar o seed de novo não pode
        // trocar a senha do dono pela padrão, que está no README público.
        User::firstOrCreate(['email' => 'dono@exemplo.com.br'], fn () => [
            'property_id' => $property->id,
            'name' => 'Dono da Pousada',
            'password' => $this->ownerPassword(),
        ]);
    }

    /**
     * Nome, textos e contato da pousada, como no site deles.
     *
     * @return array<string, mixed>
     */
    private function details(): array
    {
        return [
            'name' => 'Espaço Bela Vista',
            'tagline' => 'Piscina, fogueira e vista para as montanhas em Mairiporã',
            'description' => 'Uma pousada ao lado de uma reserva ambiental, a 5 minutos do centro de Mairiporã e a 45 do aeroporto de Guarulhos. Chalé com hidromassagem para o casal, espaço com piscina e salão de festas para a família e opções econômicas para quem vem a um casamento na região. Reserve direto com a gente, sem taxa de aplicativo.',
            'highlights' => [
                ['title' => 'Para a família', 'text' => 'Espaço privativo com piscina de escadaria larga, fogueira e salão com sinuca e churrasqueira.'],
                ['title' => 'Para o casal', 'text' => 'Chalé de 98 m² com cama king, banheira de hidromassagem e vista para as montanhas.'],
                ['title' => 'Reserva direta', 'text' => 'Você fala com a gente pelo WhatsApp, sem intermediário e sem taxa de aplicativo.'],
            ],
            'story_title' => 'Do café da manhã à fogueira',
            'story' => [
                [
                    'time' => '8h', 'period' => 'Manhã', 'title' => 'Acordar sem pressa', 'image' => '/fotos/lugar-1.jpg',
                    'text' => 'Vista para o vale e a represa, pátio de palmeiras imperiais e café da manhã servido na acomodação, se você quiser.',
                    'couple' => 'Acordar na cama king da suíte master, com vista para as montanhas. O café da manhã chega na acomodação, se você quiser.',
                    'family' => 'As crianças acordam com o pátio de palmeiras imperiais lá fora. O café da manhã chega na acomodação, se você quiser.',
                ],
                [
                    'time' => '15h', 'period' => 'Tarde', 'title' => 'A tarde inteira pela frente', 'image' => '/fotos/espaco-2.jpg',
                    'text' => 'Piscina com escadaria larga, churrasqueira no salão de festas ou um livro na varanda do chalé.',
                    'couple' => 'Um livro da sala do chalé e a varanda com cadeiras para ver o dia passar.',
                    'family' => 'Piscina com escadaria larga para os pequenos entrarem com calma e churrasqueira pronta no salão de festas.',
                ],
                [
                    'time' => '21h', 'period' => 'Noite', 'title' => 'Quando o deck acende', 'image' => '/fotos/espaco-4.jpg',
                    'text' => 'Fogueira, deck com LED, sinuca no salão ou a banheira de hidromassagem do chalé.',
                    'couple' => 'Jantar na cozinha do chalé e, para fechar, a banheira de hidromassagem extra grande.',
                    'family' => 'Roda de fogueira, deck iluminado e sinuca no salão para quem não quer dormir cedo.',
                ],
            ],
            'extras' => [
                ['title' => 'Café da manhã', 'text' => 'Café da manhã completo servido na sua acomodação.'],
                ['title' => 'Decoração surpresa', 'text' => 'Pacotes de decoração para surpreender quem você ama.'],
                ['title' => 'Fogueira pronta', 'text' => 'Lenha, montagem e a fogueira acesa para a roda da noite.'],
                ['title' => 'Churrasqueiro ou cozinheira', 'text' => 'Um profissional prepara a comida com os ingredientes que você trouxer.'],
                ['title' => 'Receitas sob encomenda', 'text' => 'Do waffle com chocolate ao caldo verde: é só pedir com antecedência.'],
                ['title' => 'Limpeza e lavanderia', 'text' => 'Limpezas extras e lavagem de roupas para estadias longas.'],
                ['title' => 'Passeios e fotos', 'text' => 'Passeios por Mairiporã com registro fotográfico das paisagens.'],
            ],
            'email' => 'contato@ivoforti.com',
            'phone' => '1146043867',
            'whatsapp' => '5511950211636',
            'address' => 'R. Custódio, 180',
            'city' => 'Mairiporã',
            'state' => 'SP',
            'primary_color' => '#2f6f4f',
        ];
    }

    /**
     * Devolve à demonstração o nome, as cores e o contato originais (o painel deixa mudar tudo isso).
     */
    public function restoreIdentity(Property $property): void
    {
        $property->update([...array_intersect_key($this->details(), array_flip(SiteSettings::IDENTITY)), 'logo_path' => null]);
    }

    /**
     * As acomodações do site da pousada: as quatro de estadia e o salão de festas, reservado por hora. Cria só as que não existem (pelo slug), com fotos e
     * a diária de réveillon; as que já existem ficam como o dono deixou.
     */
    public function accommodations(Property $property): void
    {
        $this->accommodation($property, 'chale-master', [
            'name' => 'Chalé Master',
            'best_for' => 'casal',
            'description' => 'Chalé de 98 m² com vista para as montanhas, o preferido dos casais e das noivas que se arrumam para o casamento. Suíte master com cama king, closet e TV com streamings; banheiro em porcelanato com banheira de hidromassagem extra grande para o casal. Sala com livros, cozinha completa compacta, sala de jantar, segundo quarto com duas camas de solteiro e varandinha com cadeiras. Acesso por escada e ponte de madeira, perto do centro e dos espaços de casamento.',
            'amenities' => ['Vista para as montanhas', 'Hidromassagem para casal', 'Cama king com closet', 'Cozinha completa', 'TV com streamings', 'Varanda', 'Perto do centro'],
            'max_guests' => 4,
            'base_price' => 49700,
            'weekend_price' => 99700,
            'sort_order' => 1,
        ], [
            ['/fotos/chale-1.jpg', 'Varanda com vista para as montanhas'],
            ['/fotos/chale-2.jpg', 'Banheira de hidromassagem para o casal'],
            ['/fotos/chale-3.jpg', 'Suíte master com cama king'],
            ['/fotos/chale-4.jpg', 'Suíte com iluminação romântica'],
            ['/fotos/chale-5.jpg', 'Ponte de madeira na entrada do chalé'],
            ['/fotos/chale-6.jpg', 'Sala de estar'],
            ['/fotos/chale-7.jpg', 'Sala de jantar com saída para a varanda'],
            ['/fotos/chale-8.jpg', 'Banheiro da suíte em porcelanato'],
            ['/fotos/chale-9.jpg', 'Vista da janela'],
            ['/fotos/chale-10.jpg', 'Quarto extra com duas camas de solteiro'],
            ['/fotos/chale-11.jpg', 'Box do banheiro da suíte'],
        ], holiday: 129700);

        $this->accommodation($property, 'espaco-bela-vista', [
            'name' => 'Espaço Bela Vista',
            'best_for' => 'familia',
            // O pacote do Espaço inclui o apartamento Bela, que também é alugado sozinho.
            'shared_unit' => 'apartamento-bela',
            'description' => 'Área privativa para descansar em família ou comemorar uma data especial. Inclui o apartamento Bela, de 30 m², com sala (sofá e TV 32" com Fire TV e streamings), quarto de casal, quarto com dois beliches e banheiro. Do lado de fora, pátio com palmeiras imperiais, deck com LED à noite, piscina tamanho família com escadaria larga para as crianças, área da fogueira e salão de festas com cozinha, churrasqueira, sinuca, TV e luzes de festa. Acesso por escadas e rampa.',
            'amenities' => ['Piscina familiar com escadaria larga', 'Salão de festas com cozinha e churrasqueira', 'Mesa de sinuca', 'Área de fogueira', 'Deck com iluminação de LED', 'TV 32" com Fire TV e streamings', 'Rampa de acesso', 'Café da manhã opcional'],
            'max_guests' => 6,
            'base_price' => 49700,
            'weekend_price' => 99700,
            'sort_order' => 2,
        ], [
            ['/fotos/espaco-1.jpg', 'Piscina e salão de festas à noite'],
            ['/fotos/espaco-2.jpg', 'Piscina com vista para as montanhas'],
            ['/fotos/espaco-3.jpg', 'Deck com espreguiçadeiras e escadaria larga da piscina'],
            ['/fotos/espaco-4.jpg', 'Piscina com a iluminação de LED do deck'],
            ['/fotos/espaco-5.jpg', 'Salão de festas com churrasqueira e sinuca'],
            ['/fotos/espaco-6.jpg', 'Salão com churrasqueira, cozinha e luzes de festa'],
            ['/fotos/espaco-7.jpg', 'Área da fogueira'],
            ['/fotos/espaco-8.jpg', 'Pátio com palmeiras imperiais'],
            ['/fotos/espaco-9.jpg', 'Deck iluminado à noite'],
            ['/fotos/espaco-10.jpg', 'Piscina entre palmeiras e montanhas'],
            ['/fotos/espaco-11.jpg', 'Pátio de entrada do espaço'],
            ['/fotos/espaco-12.jpg', 'Sinuca no salão com vista para a piscina'],
        ]);

        $this->accommodation($property, 'apto-bela-piscina', [
            'name' => 'Apto Bela Piscina',
            'shared_unit' => 'apartamento-bela',
            'description' => 'Apartamento de 30 m² de frente para a piscina, com janelas para a floresta: ideal para um casal com filhos. Quarto de casal, quarto com beliche e cama de solteiro, sala com sofá e TV com streamings e banheiro privativo. A área de lazer com piscina é compartilhada; se quiser ela só para vocês, reserve o Espaço Bela Vista. Acesso por escadas; vaga rotativa na garagem, se houver.',
            'amenities' => ['De frente para a piscina', 'Vista para a floresta', 'Quarto de casal e quarto com beliche', 'Sala com TV e streamings', 'Banheiro privativo', 'Área de lazer compartilhada'],
            'max_guests' => 5,
            'base_price' => 35000,
            'weekend_price' => 50000,
            'sort_order' => 3,
        ], [
            ['/fotos/apto-1.jpg', 'Piscina em frente ao apartamento'],
            ['/fotos/apto-2.jpg', 'Quarto de casal'],
            ['/fotos/apto-3.jpg', 'Sala com TV e streamings'],
            ['/fotos/apto-4.jpg', 'Entrada do apartamento'],
            ['/fotos/apto-5.jpg', 'Pia do banheiro'],
            ['/fotos/apto-6.jpg', 'Banheiro'],
        ], holiday: 100000);

        $this->accommodation($property, 'suite-vista-do-vale', [
            'name' => 'Suíte Vista do Vale',
            'description' => 'Nossa opção mais econômica: apartamento de 32 m² com vista para o vale e a represa, ideal para quem vem a um casamento na região e quer pernoitar perto. Quarto com cama de casal, guarda-roupa e sofá-cama, cozinha compacta e banheiro com chuveiro quente. Não tem área de lazer, mas tem um quintal compartilhado. Acesso por escadas; sem garagem, com vagas na rua em frente.',
            'amenities' => ['Vista para o vale e a represa', 'Cama de casal e sofá-cama', 'Cozinha compacta', 'Chuveiro quente', 'Quintal compartilhado', 'Wi-Fi'],
            'max_guests' => 3,
            'base_price' => 29700,
            'weekend_price' => 39700,
            'sort_order' => 4,
        ], [
            ['/fotos/suite-1.jpg', 'Vista para o vale e a represa'],
            ['/fotos/suite-2.jpg', 'Quarto com cama de casal e sofá-cama'],
            ['/fotos/suite-3.jpg', 'Banheiro com chuveiro quente'],
            ['/fotos/lugar-1.jpg', 'A represa vista da pousada'],
        ], holiday: 99700);

        // O salão do Espaço também é alugado para festas, por hora. Fica no mesmo imóvel do Espaço
        // e do apartamento: um evento ocupa a noite deles naquele dia. Preço e horários de exemplo.
        $this->accommodation($property, 'salao-de-festas', [
            'name' => 'Salão de Festas',
            'shared_unit' => 'apartamento-bela',
            'booking_mode' => 'hourly',
            'description' => 'Para aniversários, chá de bebê e confraternizações: salão com cozinha, churrasqueira, sinuca, TV e luzes de festa, com a piscina, o deck iluminado e a área da fogueira do Espaço Bela Vista. Reservado por hora.',
            'amenities' => ['Salão com cozinha e churrasqueira', 'Mesa de sinuca', 'Piscina e deck com LED', 'Área de fogueira', 'TV e luzes de festa', 'Até 40 pessoas'],
            'max_guests' => 40,
            'base_price' => 0,
            'hourly_price' => 15000,
            'min_hours' => 4,
            'opens_at' => '09:00',
            'closes_at' => '23:00',
            'cleaning_fee' => 20000,
            'sort_order' => 5,
        ], [
            ['/fotos/espaco-6.jpg', 'Salão com churrasqueira, cozinha e luzes de festa'],
            ['/fotos/espaco-5.jpg', 'Salão de festas com churrasqueira e sinuca'],
            ['/fotos/espaco-12.jpg', 'Sinuca com vista para a piscina'],
            ['/fotos/espaco-1.jpg', 'Piscina e salão à noite'],
            ['/fotos/espaco-9.jpg', 'Deck iluminado'],
            ['/fotos/espaco-7.jpg', 'Área da fogueira'],
        ]);
    }

    /**
     * Apaga as acomodações da pousada (com fotos e temporadas) e cria as originais de novo.
     * Usado pelo reset diário da demonstração, depois de apagar os pedidos.
     */
    public function restoreAccommodations(Property $property): void
    {
        $mediaIds = $property->accommodations()->with('photos')->get()
            ->flatMap(fn (Accommodation $accommodation) => $accommodation->photos->map(fn ($photo) => Media::idFromPath($photo->path)))
            ->filter();
        $property->accommodations()->delete();
        Media::whereKey($mediaIds)->delete();
        $this->accommodations($property);
    }

    /**
     * Cria a acomodação, se ainda não existe, com as fotos da lista e a diária de réveillon.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{0: string, 1: string}>  $photos  caminho e legenda
     */
    private function accommodation(Property $property, string $slug, array $data, array $photos, ?int $holiday = null): void
    {
        if ($property->accommodations()->where('slug', $slug)->exists()) {
            return;
        }

        $accommodation = $property->accommodations()->create(['slug' => $slug] + $data + [
            'shared_unit' => null,
            'best_for' => null,
            // Provisório: acréscimo por pessoa a mais ainda não informado pela pousada.
            'included_guests' => 2,
            'extra_guest_price' => null,
            'min_nights' => 1,
        ]);

        foreach ($photos as $i => [$path, $caption]) {
            $accommodation->photos()->create(['path' => $path, 'caption' => $caption, 'sort_order' => $i]);
        }

        // Diária de feriado publicada no site da pousada (o Espaço tem pacotes à parte, sob consulta).
        if ($holiday) {
            $accommodation->seasonalPrices()->create([
                'name' => 'Feriado (réveillon)',
                'starts_on' => now()->year.'-12-26',
                'ends_on' => (now()->year + 1).'-01-02',
                'price' => $holiday,
            ]);
        }
    }

    /**
     * Fotos da pousada, só quando ela ainda não tem nenhuma. As marcadas como destaque vão para a capa.
     *
     * @param  list<array{0: string, 1: string, 2: bool}>  $photos  caminho, legenda e destaque
     */
    private function photos(Property $property, array $photos): void
    {
        if ($property->photos()->exists()) {
            return;
        }

        foreach ($photos as $i => [$path, $caption, $featured]) {
            $property->photos()->create(['path' => $path, 'caption' => $caption, 'featured' => $featured, 'sort_order' => $i]);
        }
    }

    /**
     * Em produção não existe senha padrão: sem SEED_OWNER_PASSWORD o seed para.
     */
    private function ownerPassword(): string
    {
        $password = env('SEED_OWNER_PASSWORD');

        if (! $password && app()->isProduction()) {
            throw new \RuntimeException('Defina SEED_OWNER_PASSWORD para criar o dono da pousada em produção.');
        }

        return $password ?: 'password';
    }
}
