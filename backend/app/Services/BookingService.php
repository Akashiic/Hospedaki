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
     * @param  list<int>  $accommodationIds
     * @return array{check_in: string, check_out: string, nights: int, total: int, items: list<array{accommodation_id: int, name: string, subtotal: int, nights: list<array{date: string, price: int}>}>}
     */
    public function quote(Property $property, array $accommodationIds, string $checkIn, string $checkOut, int $guests): array
    {
        $checkIn = CarbonImmutable::parse($checkIn);
        $checkOut = CarbonImmutable::parse($checkOut);
        $accommodations = $this->findAccommodations($property, $accommodationIds);

        $this->assertRules($accommodations, $checkIn, $checkOut, $guests);

        $items = $accommodations->map(function (Accommodation $accommodation) use ($checkIn, $checkOut) {
            $quote = $this->pricing->quote($accommodation, $checkIn, $checkOut);

            return [
                'accommodation_id' => $accommodation->id,
                'name' => $accommodation->name,
                'subtotal' => $quote['total'],
                'nights' => $quote['nights'],
            ];
        })->values()->all();

        return [
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => (int) $checkIn->diffInDays($checkOut),
            'total' => array_sum(array_column($items, 'subtotal')),
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
            Accommodation::whereKey($data['accommodation_ids'])->lockForUpdate()->get();

            $quote = $this->quote($property, $data['accommodation_ids'], $data['check_in'], $data['check_out'], $data['guests']);

            $booking = $property->bookings()->create([
                'status' => BookingStatus::Pending,
                'check_in' => $quote['check_in'],
                'check_out' => $quote['check_out'],
                'guests' => $data['guests'],
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
                    'check_out' => "{$accommodation->name} exige no mínimo {$accommodation->min_nights} diária(s).",
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
