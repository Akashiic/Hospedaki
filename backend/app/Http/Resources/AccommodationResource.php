<?php

namespace App\Http\Resources;

use App\Models\Accommodation;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Accommodation */
class AccommodationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Data da última mudança: o mapa do site avisa o Google do que mudou.
            'updated_at' => $this->updated_at?->toAtomString(),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            // Indicação da pousada para a escolha "a dois / com a família" da capa.
            'best_for' => $this->best_for,
            // Acomodações com o mesmo valor ficam no mesmo imóvel e não são reservadas juntas.
            'shared_unit' => $this->shared_unit,
            'amenities' => $this->amenities ?? [],
            // Ficha para o Google (tipo, quartos, banheiros, área, camas, pets, fumantes); null = não preenchida.
            'details' => Accommodation::normalizeDetails($this->details),
            'max_guests' => $this->max_guests,
            // Por diária (estadia) ou por hora (day use, eventos).
            'booking_mode' => $this->booking_mode,
            'check_in_time' => $this->checkInTime(),
            'check_out_time' => $this->checkOutTime(),
            'hourly_price' => $this->hourly_price,
            'min_hours' => $this->min_hours,
            'opens_at' => $this->opensAt(),
            'closes_at' => $this->closesAt(),
            // Pessoas incluídas na diária e acréscimo por pessoa a mais, por diária (centavos); nulo = não informado.
            'included_guests' => $this->included_guests,
            'base_price' => $this->base_price,
            'weekend_price' => $this->weekend_price,
            'extra_guest_price' => $this->extra_guest_price,
            'min_nights' => $this->min_nights,
            // Taxa de limpeza por estadia (centavos) e descontos (%) para 7 e 28 noites ou mais.
            'cleaning_fee' => $this->cleaning_fee,
            'weekly_discount' => $this->weekly_discount,
            'monthly_discount' => $this->monthly_discount,
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'url' => PhotoUrl::from($photo->path),
                'caption' => $photo->caption,
            ])),
            'payment' => $this->payment(),
        ];
    }

    /**
     * Como se paga online (vazio = pedido que o dono confirma) e a promoção do Pix valendo agora,
     * com o fim do ciclo atual. Em loop, o site recomeça o timer sozinho a cada loop_hours.
     *
     * @return array{methods: list<string>, pix_discount: int, promo_ends_at: ?string, promo_loop_hours: ?int}
     */
    private function payment(): array
    {
        $methods = app(PaymentService::class)->methodsFor($this->property, [$this->resource]);
        $promo = in_array(Payment::PIX, $methods, true) ? $this->pixPromo() : null;

        return [
            'methods' => $methods,
            'pix_discount' => $promo['percent'] ?? 0,
            'promo_ends_at' => ($promo['ends_at'] ?? null)?->toAtomString(),
            'promo_loop_hours' => ($promo['loop'] ?? false) ? $this->promo_loop_hours : null,
        ];
    }
}
