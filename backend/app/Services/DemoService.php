<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\BlockedDate;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pousada de demonstração (config/demo.php): login aberto no painel e dados que voltam
 * ao estado inicial todo dia, para quem visita sempre encontrar a demo arrumada.
 */
class DemoService
{
    /** Guarda a data (no fuso da pousada) do último reset. */
    private const RESET_KEY = 'demo:reset-on';

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

        // Se outro pedido já está resetando, este segue sem esperar.
        Cache::lock(self::RESET_KEY.':lock', 30)->get(function () use ($property, $today) {
            if (Cache::get(self::RESET_KEY) !== $today) {
                $this->reset($property);
                Cache::forever(self::RESET_KEY, $today);
            }
        });
    }

    /**
     * Apaga pedidos, bloqueios e sessões da pousada e cria os exemplos de novo, com datas a partir de hoje.
     */
    public function reset(Property $property): void
    {
        DB::transaction(function () use ($property) {
            $property->bookings()->delete();
            BlockedDate::whereIn('accommodation_id', $property->accommodations()->select('id'))->delete();
            $this->user($property)->tokens()->delete();
            // O layout da página inicial volta ao padrão: um visitante pode ter escondido tudo.
            $settings = $property->settings ?? [];
            unset($settings['home_sections']);
            $property->update(['settings' => $settings ?: null]);

            $accommodations = $property->accommodations()->where('active', true)->get();

            if ($accommodations->isEmpty()) {
                return;
            }

            // Com uma acomodação só, os exemplos caem todos nela; as datas não se cruzam.
            $first = $accommodations->first();
            $second = $accommodations->get(1) ?? $first;

            // Uma de cada situação da aba Reservas: cancelada antiga, concluída, hospedados agora...
            $this->sample($property, $second, -15, -13, BookingStatus::Cancelled, 'Bruno Teixeira', 2, null, hoursAgo: 24 * 25);
            $this->sample($property, $second, -8, -5, BookingStatus::Confirmed, 'Família Rocha', 4, 'Chegamos depois das 20h.', hoursAgo: 24 * 20);
            $this->sample($property, $first, -1, 2, BookingStatus::Confirmed, 'Marina e Paulo', 2, 'Lua de mel!', hoursAgo: 24 * 12);
            // ...e as que estão por vir.
            $this->sample($property, $first, 3, 4, BookingStatus::Declined, 'Ricardo Alves', 2, 'Dá para fazer o check-in às 22h?', hoursAgo: 52);
            $this->sample($property, $second, 5, 7, BookingStatus::Confirmed, 'Ana Souza', 2, 'Aniversário de casamento.', hoursAgo: 30);
            $this->sample($property, $first, 9, 12, BookingStatus::Pending, 'Família Oliveira', 5, 'Vamos com um cachorro pequeno, pode?', hoursAgo: 5);
            $this->sample($property, $second, 12, 14, BookingStatus::Pending, 'Carlos Lima', 2, null, hoursAgo: 1);

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
        });
    }

    /**
     * Pedido de exemplo com o valor calculado pelas regras de preço de verdade.
     * Não passa por BookingService::request para não mandar e-mail ao dono.
     */
    private function sample(Property $property, Accommodation $accommodation, int $inDays, int $outDays, BookingStatus $status, string $name, int $guests, ?string $notes, int $hoursAgo): void
    {
        $today = $property->today();
        $guests = min($guests, $accommodation->max_guests);
        $quote = $this->bookings->quote($property, [$accommodation->id], $today->addDays($inDays)->toDateString(), $today->addDays($outDays)->toDateString(), $guests);

        $booking = $property->bookings()->create([
            'status' => $status,
            'check_in' => $quote['check_in'],
            'check_out' => $quote['check_out'],
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
}
