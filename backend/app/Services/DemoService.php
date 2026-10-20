<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\BlockedDate;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Pousada de demonstração (config/demo.php): login aberto no painel e dados que voltam
 * ao estado inicial todo dia, para quem visita sempre encontrar a demo arrumada.
 */
class DemoService
{
    /** Guarda a data (no fuso da pousada) do último reset. */
    private const RESET_KEY = 'demo:reset-on';

    /**
     * Tempo máximo do reset, em segundos. São cerca de 115 consultas, e com a API e o banco em
     * regiões diferentes cada uma leva uns 250 ms: passa dos 30 s padrão do PHP.
     */
    private const RESET_TIME_LIMIT = 180;

    /** Sorteio do histórico (ver history()). */
    private Randomizer $rng;

    public function __construct(private readonly BookingService $bookings) {}

    public function isDemo(Property $property): bool
    {
        return config('demo.enabled') && $property->slug === config('demo.property_slug');
    }

    /**
     * A pousada de demonstração, ou null com o modo desligado.
     */
    public function property(): ?Property
    {
        if (! config('demo.enabled')) {
            return null;
        }

        return Property::where('slug', config('demo.property_slug'))->where('active', true)->first();
    }

    /**
     * Usuário do login aberto. A senha é aleatória: entra-se só pelo botão de demonstração.
     */
    public function user(Property $property): User
    {
        return User::firstOrCreate(['email' => config('demo.user_email')], fn () => [
            'property_id' => $property->id,
            'name' => 'Dono (demonstração)',
            'password' => Str::random(40),
        ]);
    }

    /**
     * Reseta a demo na primeira visita de cada dia. Chamado pelo site e pelo login da demo,
     * porque no plano gratuito do Render não há agendador rodando o tempo todo.
     *
     * O reset roda depois que a resposta sai (quem chegou primeiro não espera) e com um limite
     * de tempo maior: cortado no meio pelo limite padrão, ele voltava atrás e cada visita
     * seguinte tentava de novo e caía do mesmo jeito.
     */
    public function resetIfStale(Property $property): void
    {
        if (! $this->isDemo($property)) {
            return;
        }

        $today = $property->today()->toDateString();

        if (Cache::get(self::RESET_KEY) === $today) {
            return;
        }

        app()->terminating(function () use ($property, $today) {
            @set_time_limit(self::RESET_TIME_LIMIT);

            // Se outro pedido já está resetando, este segue sem esperar. A trava dura mais que
            // o próprio reset, para dois pedidos nunca resetarem ao mesmo tempo.
            Cache::lock(self::RESET_KEY.':lock', self::RESET_TIME_LIMIT + 20)->get(function () use ($property, $today) {
                if (Cache::get(self::RESET_KEY) !== $today) {
                    $this->reset($property);
                    Cache::forever(self::RESET_KEY, $today);
                }
            });
        });
    }

    /**
     * Desconto no Pix de exemplo, para o visitante ver o selo e o timer: no Chalé, 10% até a
     * meia-noite, recomeçando todo dia; no Espaço, 5% sem prazo. Os pagamentos são simulados.
     */
    private function promotions(Property $property): void
    {
        $midnight = $property->today()->addDay()->utc();

        $property->accommodations()->where('slug', 'chale-master')
            ->update(['pix_discount' => 10, 'promo_ends_at' => $midnight, 'promo_loop' => true, 'promo_loop_hours' => 24]);
        $property->accommodations()->where('slug', 'espaco-bela-vista')
            ->update(['pix_discount' => 5, 'promo_ends_at' => null, 'promo_loop' => false]);
    }

    /**
     * Apaga pedidos, bloqueios e sessões da pousada e cria os exemplos de novo, com datas a partir de hoje.
     */
    public function reset(Property $property): void
    {
        DB::transaction(function () use ($property) {
            $property->bookings()->delete();
            BlockedDate::whereIn('accommodation_id', $property->accommodations()->select('id'))->delete();
            // Só as sessões de dias anteriores: quem acabou de entrar (o reset roda depois do
            // login) continua logado.
            $this->user($property)->tokens()->where('created_at', '<', $property->today()->utc())->delete();
            // Layout da home, textos das páginas e SEO voltam ao padrão: um visitante pode ter
            // escondido tudo ou trocado o nome da pousada.
            $settings = $property->settings ?? [];
            unset($settings['home_sections'], $settings['pages'], $settings['seo'], $settings['payments'], $settings['social'], $settings['themes_waitlist']);
            $property->update(['settings' => $settings ?: null]);

            // Visitantes podem editar, ocultar ou excluir acomodações pelo painel: voltam as originais.
            if ($property->slug === DatabaseSeeder::SLUG) {
                app(DatabaseSeeder::class)->restoreIdentity($property);
                app(DatabaseSeeder::class)->restoreAccommodations($property);
            }

            $this->promotions($property);

            $accommodations = $property->accommodations()->where('active', true)->where('booking_mode', Accommodation::NIGHTLY)->get();

            if ($accommodations->isEmpty()) {
                return;
            }

            // Com uma acomodação só, os exemplos caem todos nela; as datas não se cruzam.
            $first = $accommodations->first();
            $second = $accommodations->get(1) ?? $first;

            // Uma de cada situação da aba Reservas: cancelada antiga, concluída, hospedados agora...
            $this->sample($property, $second, -15, -13, BookingStatus::Cancelled, 'Bruno Teixeira', 2, null, hoursAgo: 24 * 25);
            $this->sample($property, $second, -8, -5, BookingStatus::Confirmed, 'Família Rocha', 4, 'Chegamos depois das 20h.', hoursAgo: 24 * 20, arrival: '20:30');
            $this->sample($property, $first, -1, 2, BookingStatus::Confirmed, 'Marina e Paulo', 2, 'Lua de mel!', hoursAgo: 24 * 12, arrival: '15:00');
            // ...e as que estão por vir.
            $this->sample($property, $first, 3, 4, BookingStatus::Declined, 'Ricardo Alves', 2, 'Dá para fazer o check-in às 22h?', hoursAgo: 52, arrival: '22:00');
            $this->sample($property, $second, 5, 7, BookingStatus::Confirmed, 'Ana Souza', 2, 'Aniversário de casamento.', hoursAgo: 30, arrival: '16:30');
            $this->sample($property, $first, 9, 12, BookingStatus::Pending, 'Família Oliveira', 5, 'Vamos com um cachorro pequeno, pode?', hoursAgo: 5, arrival: '19:00');
            $this->sample($property, $second, 12, 14, BookingStatus::Pending, 'Carlos Lima', 2, null, hoursAgo: 1);

            // Uma festa no espaço reservado por hora, se a pousada tiver um.
            $hall = $property->accommodations()->where('active', true)->where('booking_mode', Accommodation::HOURLY)->first();
            if ($hall) {
                $this->sampleEvent($property, $hall, 17, '14:00', '20:00', 'Aniversário da Júlia', 30, hoursAgo: 48);
            }

            $today = $property->today();
            BlockedDate::create([
                'accommodation_id' => $first->id,
                'starts_on' => $today->addDays(20)->toDateString(),
                'ends_on' => $today->addDays(22)->toDateString(),
                'reason' => 'Reserva feita no Airbnb',
            ]);
            BlockedDate::create([
                'accommodation_id' => $second->id,
                'starts_on' => $today->addDays(25)->toDateString(),
                'ends_on' => $today->addDays(26)->toDateString(),
                'reason' => 'Manutenção',
            ]);

            // Um ano de reservas passadas (até ontem), nas datas livres que sobraram, para as
            // Estatísticas terem o que mostrar.
            $this->history($property);
        });
    }

    /**
     * Pedido de exemplo com o valor calculado pelas regras de preço de verdade.
     * Não passa por BookingService::request para não mandar e-mail ao dono.
     */
    private function sample(Property $property, Accommodation $accommodation, int $inDays, int $outDays, BookingStatus $status, string $name, int $guests, ?string $notes, int $hoursAgo, ?string $arrival = null): void
    {
        $today = $property->today();
        $guests = min($guests, $accommodation->max_guests);
        $quote = $this->bookings->quote($property, [$accommodation->id], $today->addDays($inDays)->toDateString(), $today->addDays($outDays)->toDateString(), $guests);

        $booking = $property->bookings()->create([
            'status' => $status,
            'check_in' => $quote['check_in'],
            'check_out' => $quote['check_out'],
            'arrival_time' => $arrival,
            'guests' => $guests,
            'extra_guests_unpriced' => $quote['extra_guests_unpriced'],
            'guest_name' => $name,
            'guest_phone' => '11999990000',
            'notes' => $notes,
            'total' => $quote['total'],
            'confirmed_at' => $status === BookingStatus::Confirmed ? now()->subHours($hoursAgo - 1) : null,
            'cancelled_at' => in_array($status, [BookingStatus::Declined, BookingStatus::Cancelled], true) ? now()->subHours($hoursAgo - 1) : null,
        ]);
        $booking->accommodations()->attach($accommodation->id, ['subtotal' => $quote['items'][0]['subtotal']]);

        // Pedidos chegando em horas diferentes, como num dia normal.
        $booking->forceFill(['created_at' => now()->subHours($hoursAgo)])->saveQuietly();
    }

    /**
     * Festa de exemplo num espaço reservado por hora, já confirmada.
     */
    private function sampleEvent(Property $property, Accommodation $hall, int $inDays, string $start, string $end, string $name, int $guests, int $hoursAgo): void
    {
        $date = $property->today()->addDays($inDays)->toDateString();
        $quote = $this->bookings->quote($property, [$hall->id], $date, null, min($guests, $hall->max_guests), $start, $end);

        $booking = $property->bookings()->create([
            'status' => BookingStatus::Confirmed,
            'check_in' => $date,
            'check_out' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'guests' => min($guests, $hall->max_guests),
            'guest_name' => $name,
            'guest_phone' => '11999990000',
            'notes' => 'Vamos levar o bolo e os doces.',
            'total' => $quote['total'],
            'confirmed_at' => now()->subHours($hoursAgo - 1),
        ]);
        $booking->accommodations()->attach($hall->id, ['subtotal' => $quote['total']]);
        $booking->forceFill(['created_at' => now()->subHours($hoursAgo)])->saveQuietly();
    }

    /**
     * Histórico de exemplo: estadias e festas dos últimos 12 meses, até hoje, nas datas que os
     * exemplos do dia a dia e os bloqueios deixaram livres. Sorteio com semente fixa;
     * mais movimento em dezembro, janeiro e julho. Gravado em lote (poucas consultas), porque o
     * reset roda numa requisição e o banco fica longe da API.
     */
    private function history(Property $property): void
    {
        $accommodations = $property->accommodations()->where('active', true)->get();
        $today = $property->today();
        $start = $today->subMonths(12)->startOfMonth();
        $end = $today;
        // Sorteio próprio, com semente fixa: o mesmo histórico todo dia. O mt_rand global não serve:
        // qualquer código pode ressemeá-lo no meio (o Faker faz isso ao ser coletado pelo GC).
        $rng = $this->rng = new Randomizer(new Mt19937(2026));

        $names = ['Patrícia Gomes', 'Rafael e Bia', 'Família Martins', 'Juliana Rocha', 'Thiago Nunes', 'Camila e Pedro', 'Família Souza', 'Fernanda Lima',
            'Lucas Andrade', 'Marcos e Ana', 'Família Costa', 'Beatriz Alves', 'Gustavo Pires', 'Larissa e João', 'Família Ribeiro', 'Renata Dias'];
        $busy = [1 => 1.6, 7 => 1.5, 12 => 1.7, 2 => 1.2, 10 => 1.1];
        $rows = [];
        $links = [];
        $takenByUnit = [];
        $unitOf = $accommodations->mapWithKeys(fn (Accommodation $a) => [$a->id => $a->shared_unit ?? "a{$a->id}"]);

        // Datas já ocupadas pelos exemplos do dia a dia (e pelos bloqueios): o histórico não passa por cima.
        foreach ($property->bookings()->with('accommodations:id')->get() as $booking) {
            $last = $booking->isHourly() ? $booking->check_in : $booking->check_out->subDay();
            foreach ($booking->accommodations as $acc) {
                for ($d = $booking->check_in->toImmutable(); $d->lte($last); $d = $d->addDay()) {
                    $takenByUnit[$unitOf[$acc->id] ?? "a{$acc->id}"][$d->toDateString()] = true;
                }
            }
        }
        foreach (BlockedDate::whereIn('accommodation_id', $accommodations->pluck('id'))->get() as $block) {
            for ($d = $block->starts_on->toImmutable(); $d->lte($block->ends_on); $d = $d->addDay()) {
                $takenByUnit[$unitOf[$block->accommodation_id]][$d->toDateString()] = true;
            }
        }
        $free = function (string $unit, CarbonImmutable $from, CarbonImmutable $to) use (&$takenByUnit): bool {
            for ($d = $from; $d->lt($to); $d = $d->addDay()) {
                if (isset($takenByUnit[$unit][$d->toDateString()])) {
                    return false;
                }
            }

            return true;
        };

        // Estadias: cada imóvel recebe hóspedes em intervalos sorteados, sem sobrepor.
        foreach ($accommodations->reject(fn (Accommodation $a) => $a->isHourly())->groupBy(fn ($a) => $a->shared_unit ?? "a{$a->id}") as $unit => $members) {
            $day = $start->addDays($rng->getInt(0, 6));
            while ($day->lt($end)) {
                $nights = [1, 2, 2, 2, 3, 3, 4, 5, 7][$rng->getInt(0, 8)];
                $checkOut = $day->addDays($nights);
                if ($checkOut->gt($end)) {
                    break;
                }
                if (! $free($unit, $day, $checkOut)) {
                    $day = $day->addDay();

                    continue;
                }
                $acc = $members[$rng->getInt(0, $members->count() - 1)];
                $total = 0;
                for ($d = $day; $d->lt($checkOut); $d = $d->addDay()) {
                    $total += in_array($d->dayOfWeek, PricingService::WEEKEND_NIGHTS, true) && $acc->weekend_price ? $acc->weekend_price : $acc->base_price;
                    $takenByUnit[$unit][$d->toDateString()] = true;
                }
                $total += $acc->cleaning_fee ?? 0;
                $roll = $rng->getInt(1, 100);
                $status = $roll <= 86 ? BookingStatus::Confirmed : ($roll <= 93 ? BookingStatus::Cancelled : BookingStatus::Declined);
                $created = $day->subDays($rng->getInt(2, 50))->setTime($rng->getInt(8, 22), $rng->getInt(0, 59));
                $rows[] = $this->historyRow($property, $status, $day, $checkOut, $names[$rng->getInt(0, count($names) - 1)], $rng->getInt(2, $acc->max_guests), $total, $created,
                    arrival: $rng->getInt(1, 10) <= 7 ? sprintf('%02d:%02d', $rng->getInt(14, 21), [0, 30][$rng->getInt(0, 1)]) : null);
                $links[] = [$acc->id, $total];

                // Próxima chegada: mais cedo nos meses de férias.
                $gap = (int) round($rng->getInt(2, 12) / ($busy[$day->month] ?? 1));
                $day = $checkOut->addDays(max(0, $gap));
            }
        }

        // Festas no espaço por hora: uma ou duas por mês, em dias livres no imóvel.
        foreach ($accommodations->filter(fn (Accommodation $a) => $a->isHourly()) as $hall) {
            $unit = $hall->shared_unit ?? "a{$hall->id}";
            for ($month = $start; $month->lt($end); $month = $month->addMonth()) {
                for ($n = $rng->getInt(1, 2); $n > 0; $n--) {
                    $date = $month->addDays($rng->getInt(0, $month->daysInMonth - 1));
                    if ($date->gte($end) || isset($takenByUnit[$unit][$date->toDateString()])) {
                        continue;
                    }
                    $takenByUnit[$unit][$date->toDateString()] = true;
                    $hours = $rng->getInt(max(4, $hall->min_hours), 8);
                    $startHour = $rng->getInt(11, 23 - $hours);
                    $total = $hours * ($hall->hourly_price ?? 0) + ($hall->cleaning_fee ?? 0);
                    $rows[] = $this->historyRow($property, BookingStatus::Confirmed, $date, $date, $names[$rng->getInt(0, count($names) - 1)], $rng->getInt(15, $hall->max_guests), $total,
                        $date->subDays($rng->getInt(10, 60))->setTime(10, 0), start: sprintf('%02d:00', $startHour), end: sprintf('%02d:00', $startHour + $hours));
                    $links[] = [$hall->id, $total];
                }
            }
        }

        if (! $rows) {
            return;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('bookings')->insert($chunk);
        }
        $ids = DB::table('bookings')->whereIn('code', array_column($rows, 'code'))->pluck('id', 'code');
        $pivot = [];
        foreach ($rows as $i => $row) {
            $pivot[] = ['booking_id' => $ids[$row['code']], 'accommodation_id' => $links[$i][0], 'subtotal' => $links[$i][1], 'created_at' => $row['created_at'], 'updated_at' => $row['created_at']];
        }
        foreach (array_chunk($pivot, 200) as $chunk) {
            DB::table('booking_accommodation')->insert($chunk);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function historyRow(Property $property, BookingStatus $status, CarbonImmutable $in, CarbonImmutable $out, string $name, int $guests, int $total, CarbonImmutable $created, ?string $arrival = null, ?string $start = null, ?string $end = null): array
    {
        $decided = $created->addHours($this->rng->getInt(1, 20))->utc();

        return [
            'property_id' => $property->id,
            'code' => strtoupper(Str::random(8)),
            'status' => $status->value,
            'check_in' => $in->toDateString(),
            'check_out' => $out->toDateString(),
            'arrival_time' => $arrival,
            'start_time' => $start,
            'end_time' => $end,
            'guests' => $guests,
            'extra_guests_unpriced' => 0,
            'guest_name' => $name,
            // Telefone próprio do histórico: separa dos exemplos do dia a dia.
            'guest_phone' => '11999990001',
            'total' => $total,
            'confirmed_at' => $status === BookingStatus::Declined ? null : $decided,
            'cancelled_at' => $status === BookingStatus::Confirmed ? null : $decided->addDays($this->rng->getInt(1, 10)),
            'created_at' => $created->utc(),
            'updated_at' => $created->utc(),
        ];
    }
}
