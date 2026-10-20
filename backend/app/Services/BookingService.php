<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Property;
use App\Notifications\NewBookingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Orçamento e pedido de reserva de uma ou mais acomodações da mesma pousada.
 */
class BookingService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly AvailabilityService $availability,
    ) {}

    /**
     * O subtotal de cada acomodação já inclui extra_guests_subtotal (o acréscimo das pessoas a mais
     * com valor informado). Pessoas a mais sem valor informado não entram no total e somam em
     * extra_guests_unpriced, para o site e o dono saberem que esse acréscimo fica a combinar.
     *
     * @param  list<int>  $accommodationIds
     * @return array{check_in: string, check_out: string, nights: int, total: int, extra_guests_unpriced: int, items: list<array{accommodation_id: int, name: string, subtotal: int, nights_total: int, discount: int, discount_percent: int, cleaning_fee: int, extra_guests: int, extra_guests_subtotal: int, nights: list<array{date: string, price: int}>}>}
     */
    public function quote(Property $property, array $accommodationIds, string $checkIn, ?string $checkOut, int $guests, ?string $start = null, ?string $end = null): array
    {
        $accommodations = $this->findAccommodations($property, $accommodationIds);

        if ($accommodations->contains(fn (Accommodation $a) => $a->isHourly())) {
            return $this->hourlyQuote($property, $accommodations, CarbonImmutable::parse($checkIn), $guests, $start, $end);
        }

        if (! $checkOut) {
            throw ValidationException::withMessages(['check_out' => 'Escolha a data de saída.']);
        }

        $checkIn = CarbonImmutable::parse($checkIn);
        $checkOut = CarbonImmutable::parse($checkOut);

        $this->assertRules($accommodations, $checkIn, $checkOut, $guests);

        $extraGuests = $this->allocateExtraGuests($accommodations, $guests);
        $unpriced = 0;

        $items = $accommodations->map(function (Accommodation $accommodation) use ($checkIn, $checkOut, $extraGuests, &$unpriced) {
            $quote = $this->pricing->quote($accommodation, $checkIn, $checkOut, $extraGuests[$accommodation->id]);
            $unpriced += $quote['extra_guests_unpriced'];

            return [
                'accommodation_id' => $accommodation->id,
                'name' => $accommodation->name,
                // Diárias (com pessoas a mais), menos o desconto de estadia longa, mais a taxa de limpeza.
                'subtotal' => $quote['total'],
                'nights_total' => $quote['nights_total'],
                'discount' => $quote['discount'],
                'discount_percent' => $quote['discount_percent'],
                'cleaning_fee' => $quote['cleaning_fee'],
                'extra_guests' => $quote['extra_guests'],
                'extra_guests_subtotal' => $quote['extra_guests_subtotal'],
                'nights' => $quote['nights'],
            ];
        })->values()->all();

        return [
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => (int) $checkIn->diffInDays($checkOut),
            'total' => array_sum(array_column($items, 'subtotal')),
            'extra_guests_unpriced' => $unpriced,
            'items' => $items,
        ];
    }

    /**
     * Orçamento de uma reserva por hora (day use, evento): uma acomodação só, num dia, de um
     * horário inteiro a outro, dentro do horário de funcionamento e com o mínimo de horas.
     *
     * @param  Collection<int, Accommodation>  $accommodations
     */
    private function hourlyQuote(Property $property, Collection $accommodations, CarbonImmutable $date, int $guests, ?string $start, ?string $end): array
    {
        $accommodation = $accommodations->first(fn (Accommodation $a) => $a->isHourly());

        if ($accommodations->count() > 1) {
            throw ValidationException::withMessages(['accommodation_ids' => "{$accommodation->name} é reservado por hora e precisa ser reservado sozinho."]);
        }
        if (! $start || ! $end) {
            throw ValidationException::withMessages(['start_time' => 'Escolha o horário de início e de fim.']);
        }

        [$from, $to] = [Booking::minutes($start), Booking::minutes($end)];
        $minutes = $to - $from;
        $hours = intdiv($minutes, 60);

        if ($minutes <= 0 || $minutes % 60 !== 0) {
            throw ValidationException::withMessages(['end_time' => 'Escolha horas inteiras, como das 14h às 18h.']);
        }
        if ($from < Booking::minutes($accommodation->opensAt()) || $to > Booking::minutes($accommodation->closesAt())) {
            throw ValidationException::withMessages(['start_time' => "{$accommodation->name} funciona das {$accommodation->opensAt()} às {$accommodation->closesAt()}."]);
        }
        if ($hours < $accommodation->min_hours) {
            throw ValidationException::withMessages(['end_time' => "{$accommodation->name} é reservado por no mínimo {$accommodation->min_hours} ".($accommodation->min_hours === 1 ? 'hora.' : 'horas.')]);
        }
        if ($guests > $accommodation->max_guests) {
            throw ValidationException::withMessages(['guests' => 'Número de pessoas acima da capacidade.']);
        }
        // Hoje (no fuso da pousada), só horários que ainda não começaram.
        $now = CarbonImmutable::now($property->timezone ?: Property::DEFAULT_TIMEZONE);
        if ($date->isSameDay($property->today()) && $from <= $now->hour * 60 + $now->minute) {
            throw ValidationException::withMessages(['start_time' => 'Esse horário já passou. Escolha um mais tarde.']);
        }
        if (! $this->availability->isHourAvailable($accommodation, $date, $start, $end)) {
            throw ValidationException::withMessages(['start_time' => "{$accommodation->name} não está livre nesse horário."]);
        }

        $quote = $this->pricing->hourlyQuote($accommodation, $hours);

        return [
            'check_in' => $date->toDateString(),
            'check_out' => $date->toDateString(),
            'nights' => 0,
            'start_time' => $start,
            'end_time' => $end,
            'hours' => $hours,
            'total' => $quote['total'],
            'extra_guests_unpriced' => 0,
            'items' => [[
                'accommodation_id' => $accommodation->id,
                'name' => $accommodation->name,
                'subtotal' => $quote['total'],
                'nights_total' => $quote['hours_total'],
                'discount' => 0,
                'discount_percent' => 0,
                'cleaning_fee' => $quote['cleaning_fee'],
                'extra_guests' => 0,
                'extra_guests_subtotal' => 0,
                'nights' => [],
                'hours' => $hours,
                'hourly_price' => $quote['hourly_price'],
            ]],
        ];
    }

    /**
     * Cria um pedido de reserva pendente. O dono confirma ou recusa depois.
     *
     * @param  array{accommodation_ids: list<int>, check_in: string, check_out?: ?string, start_time?: ?string, end_time?: ?string, arrival_time?: ?string, guests: int, guest_name: string, guest_email?: ?string, guest_phone: string, notes?: ?string}  $data
     */
    public function request(Property $property, array $data): Booking
    {
        $booking = DB::transaction(function () use ($property, $data) {
            // Trava as acomodações para que dois pedidos simultâneos não peguem as mesmas datas.
            // O SQLite ignora lockForUpdate(): lá a transação começa com uma escrita que não muda nada,
            // o que pega a trava de escrita do arquivo antes da leitura e põe os pedidos em fila.
            // Sem isso, dois pedidos leem juntos e o segundo a gravar cai em "database is locked" (500).
            // Essa trava vale para o arquivo todo, então já cobre as acomodações do mesmo imóvel.
            if (DB::connection()->getDriverName() === 'sqlite') {
                Accommodation::whereKey($data['accommodation_ids'])->toBase()->update(['id' => DB::raw('id')]);
            } else {
                // Trava também as que dividem o imóvel com as pedidas (shared_unit): um pedido do Espaço
                // e outro do apartamento que fica dentro dele não podem passar juntos.
                $units = Accommodation::whereKey($data['accommodation_ids'])->whereNotNull('shared_unit')->pluck('shared_unit');
                Accommodation::where(fn ($query) => $query
                    ->whereKey($data['accommodation_ids'])
                    ->orWhere(fn ($query) => $query->where('property_id', $property->id)->whereIn('shared_unit', $units)))
                    ->orderBy('id')->lockForUpdate()->get(); // sempre na mesma ordem, sem deadlock
            }

            $quote = $this->quote($property, $data['accommodation_ids'], $data['check_in'], $data['check_out'] ?? null, $data['guests'], $data['start_time'] ?? null, $data['end_time'] ?? null);
            $hourly = isset($quote['start_time']);

            $booking = $property->bookings()->create([
                'status' => BookingStatus::Pending,
                'check_in' => $quote['check_in'],
                'check_out' => $quote['check_out'],
                'start_time' => $quote['start_time'] ?? null,
                'end_time' => $quote['end_time'] ?? null,
                // Horário previsto de chegada: só faz sentido numa estadia.
                'arrival_time' => $hourly ? null : ($data['arrival_time'] ?? null),
                'guests' => $data['guests'],
                'extra_guests_unpriced' => $quote['extra_guests_unpriced'],
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'] ?? null,
                'guest_phone' => $data['guest_phone'],
                'notes' => $data['notes'] ?? null,
                'total' => $quote['total'],
            ]);

            foreach ($quote['items'] as $item) {
                $booking->accommodations()->attach($item['accommodation_id'], ['subtotal' => $item['subtotal']]);
            }

            return $booking->load('accommodations');
        });

        // Uma falha no envio do aviso não pode perder o pedido do hóspede.
        try {
            Notification::send($property->users, new NewBookingRequest($booking));
        } catch (\Throwable $e) {
            report($e);
        }

        return $booking;
    }

    public function confirm(Booking $booking): Booking
    {
        if ($booking->status !== BookingStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Só é possível confirmar pedidos pendentes.']);
        }

        $booking->update(['status' => BookingStatus::Confirmed, 'confirmed_at' => now()]);

        return $booking;
    }

    public function decline(Booking $booking): Booking
    {
        if (! in_array($booking->status, BookingStatus::holdingDates(), true)) {
            throw ValidationException::withMessages(['status' => 'Este pedido já foi encerrado.']);
        }

        $booking->update([
            'status' => $booking->status === BookingStatus::Pending ? BookingStatus::Declined : BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return $booking;
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Accommodation>
     */
    private function findAccommodations(Property $property, array $ids): Collection
    {
        $accommodations = $property->accommodations()
            ->where('active', true)
            ->whereKey(array_unique($ids))
            ->get();

        if ($accommodations->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['accommodation_ids' => 'Acomodação não encontrada.']);
        }

        return $accommodations;
    }

    /**
     * Quantas pessoas a mais (além das incluídas na diária) ficam em cada acomodação.
     *
     * Um pedido com várias acomodações não diz quem dorme onde; esta divisão serve só para
     * calcular o orçamento. Os hóspedes enchem primeiro as vagas incluídas de todas as
     * acomodações; quem sobra vai para as vagas extras, na ordem do site (sort_order, id).
     * Sem included_guests, a diária vale para a capacidade toda e não há pessoa a mais.
     * A checagem de capacidade em assertRules garante que todos cabem.
     *
     * @param  Collection<int, Accommodation>  $accommodations
     * @return array<int, int> id da acomodação => pessoas a mais
     */
    private function allocateExtraGuests(Collection $accommodations, int $guests): array
    {
        $included = $accommodations->mapWithKeys(fn (Accommodation $accommodation) => [
            $accommodation->id => min($accommodation->included_guests ?? $accommodation->max_guests, $accommodation->max_guests),
        ]);
        $left = max(0, $guests - $included->sum());
        $extra = [];

        foreach ($accommodations as $accommodation) {
            $extra[$accommodation->id] = min($left, $accommodation->max_guests - $included[$accommodation->id]);
            $left -= $extra[$accommodation->id];
        }

        return $extra;
    }

    /**
     * @param  Collection<int, Accommodation>  $accommodations
     */
    private function assertRules(Collection $accommodations, CarbonImmutable $checkIn, CarbonImmutable $checkOut, int $guests): void
    {
        $nights = (int) $checkIn->diffInDays($checkOut);

        $shared = $accommodations->whereNotNull('shared_unit')->groupBy('shared_unit')->first(fn ($group) => $group->count() > 1);
        if ($shared) {
            throw ValidationException::withMessages([
                'accommodation_ids' => $shared->pluck('name')->join(', ', ' e ').' ficam no mesmo imóvel e não podem ser reservados juntos.',
            ]);
        }

        if ($guests > $accommodations->sum('max_guests')) {
            throw ValidationException::withMessages(['guests' => 'Número de hóspedes acima da capacidade.']);
        }

        foreach ($accommodations as $accommodation) {
            // O mínimo de uma temporada (ex.: réveillon) vale quando é maior que o da acomodação.
            $minNights = max($accommodation->min_nights, $this->pricing->seasonMinNights($accommodation, $checkIn, $checkOut));
            if ($nights < $minNights) {
                throw ValidationException::withMessages([
                    'check_out' => "{$accommodation->name} exige no mínimo {$minNights} ".($minNights === 1 ? 'diária' : 'diárias').($minNights > $accommodation->min_nights ? ' nessas datas.' : '.'),
                ]);
            }

            if (! $this->availability->isAvailable($accommodation, $checkIn, $checkOut)) {
                throw ValidationException::withMessages([
                    'check_in' => "{$accommodation->name} não está disponível nessas datas.",
                ]);
            }
        }
    }
}
