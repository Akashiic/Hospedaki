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
     * @return array{check_in: string, check_out: string, nights: int, total: int, extra_guests_unpriced: int, items: list<array{accommodation_id: int, name: string, subtotal: int, extra_guests: int, extra_guests_subtotal: int, nights: list<array{date: string, price: int}>}>}
     */
    public function quote(Property $property, array $accommodationIds, string $checkIn, string $checkOut, int $guests): array
    {
        $checkIn = CarbonImmutable::parse($checkIn);
        $checkOut = CarbonImmutable::parse($checkOut);
        $accommodations = $this->findAccommodations($property, $accommodationIds);

        $this->assertRules($accommodations, $checkIn, $checkOut, $guests);

        $extraGuests = $this->allocateExtraGuests($accommodations, $guests);
        $unpriced = 0;

        $items = $accommodations->map(function (Accommodation $accommodation) use ($checkIn, $checkOut, $extraGuests, &$unpriced) {
            $quote = $this->pricing->quote($accommodation, $checkIn, $checkOut, $extraGuests[$accommodation->id]);
            $unpriced += $quote['extra_guests_unpriced'];

            return [
                'accommodation_id' => $accommodation->id,
                'name' => $accommodation->name,
                'subtotal' => $quote['total'],
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
     * Cria um pedido de reserva pendente. O dono confirma ou recusa depois.
     *
     * @param  array{accommodation_ids: list<int>, check_in: string, check_out: string, guests: int, guest_name: string, guest_email?: ?string, guest_phone: string, notes?: ?string}  $data
     */
    public function request(Property $property, array $data): Booking
    {
        $booking = DB::transaction(function () use ($property, $data) {
            // Trava as acomodações para que dois pedidos simultâneos não peguem as mesmas datas.
            // O SQLite ignora lockForUpdate(): lá a transação começa com uma escrita que não muda nada,
            // o que pega a trava de escrita do arquivo antes da leitura e põe os pedidos em fila.
            // Sem isso, dois pedidos leem juntos e o segundo a gravar cai em "database is locked" (500).
            $accommodations = Accommodation::whereKey($data['accommodation_ids']);
            DB::connection()->getDriverName() === 'sqlite'
                ? $accommodations->toBase()->update(['id' => DB::raw('id')])
                : $accommodations->lockForUpdate()->get();

            $quote = $this->quote($property, $data['accommodation_ids'], $data['check_in'], $data['check_out'], $data['guests']);

            $booking = $property->bookings()->create([
                'status' => BookingStatus::Pending,
                'check_in' => $quote['check_in'],
                'check_out' => $quote['check_out'],
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

        if ($guests > $accommodations->sum('max_guests')) {
            throw ValidationException::withMessages(['guests' => 'Número de hóspedes acima da capacidade.']);
        }

        foreach ($accommodations as $accommodation) {
            if ($nights < $accommodation->min_nights) {
                throw ValidationException::withMessages([
                    'check_out' => "{$accommodation->name} exige no mínimo {$accommodation->min_nights} ".($accommodation->min_nights === 1 ? 'diária.' : 'diárias.'),
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
