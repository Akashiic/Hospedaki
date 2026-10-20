<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminAccommodationResource;
use App\Models\Accommodation;
use App\Models\AccommodationPhoto;
use App\Models\Media;
use App\Models\Property;
use App\Models\SeasonalPrice;
use App\Services\DemoService;
use App\Services\ImageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Acomodações da pousada pelo painel: criar, editar, publicar ou ocultar, excluir, ordenar,
 * fotos e preços de temporada. Valores em dinheiro chegam e saem em centavos.
 */
class AccommodationController extends Controller
{
    /** De quanto em quanto tempo uma promoção em loop recomeça. */
    public const LOOP_HOURS = [1, 6, 12, 24, 48, 72, 168];

    public function index(Request $request): AnonymousResourceCollection
    {
        return AdminAccommodationResource::collection($this->load($request->user()->property->accommodations()->get()));
    }

    public function store(Request $request): AdminAccommodationResource
    {
        $property = $request->user()->property;
        $data = $this->validated($request, $property);

        $accommodation = DB::transaction(function () use ($property, $data) {
            $this->claimBestFor($property, $data['best_for'] ?? null);

            return $property->accommodations()->create($data + [
                'slug' => $this->uniqueSlug($property, $data['name']),
                'sort_order' => ($property->accommodations()->max('sort_order') ?? 0) + 1,
            ]);
        });

        return new AdminAccommodationResource($this->load($accommodation));
    }

    public function update(Request $request, Accommodation $accommodation): AdminAccommodationResource
    {
        $property = $this->authorizeOwner($request, $accommodation);
        $data = $this->validated($request, $property, $accommodation);

        DB::transaction(function () use ($property, $accommodation, $data) {
            $this->claimBestFor($property, $data['best_for'] ?? null, $accommodation);
            $accommodation->update($data);
        });

        return new AdminAccommodationResource($this->load($accommodation->fresh()));
    }

    /**
     * Excluir só quando nunca teve reserva: com histórico, o certo é ocultar do site
     * (as reservas antigas continuam apontando para ela).
     */
    public function destroy(Request $request, Accommodation $accommodation): JsonResponse
    {
        $this->authorizeOwner($request, $accommodation);

        if ($accommodation->bookings()->exists()) {
            throw ValidationException::withMessages([
                'accommodation' => 'Esta acomodação tem reservas no histórico e não pode ser excluída. Oculte do site para ela deixar de aparecer.',
            ]);
        }

        DB::transaction(function () use ($accommodation) {
            $mediaIds = $accommodation->photos->map(fn ($photo) => Media::idFromPath($photo->path))->filter();
            $accommodation->delete();
            $this->deleteUnusedMedia($mediaIds->all());
        });

        return response()->json(status: 204);
    }

    /**
     * Nova ordem das acomodações no site (lista completa de ids, de cima para baixo).
     */
    public function reorder(Request $request): JsonResponse
    {
        $property = $request->user()->property;
        $ids = $property->accommodations()->pluck('id')->all();

        $data = $request->validate([
            'ids' => ['required', 'array', 'size:'.count($ids)],
            'ids.*' => ['integer', 'distinct', Rule::in($ids)],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $i => $id) {
                Accommodation::whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        return response()->json(status: 204);
    }

    // ── Fotos ──

    public function storePhoto(Request $request, Accommodation $accommodation, ImageService $images, DemoService $demo): AdminAccommodationResource
    {
        $property = $this->authorizeOwner($request, $accommodation);

        // Na demonstração o site é público: fotos enviadas por visitantes apareceriam para todos.
        if ($demo->isDemo($property)) {
            throw ValidationException::withMessages(['photo' => 'Na demonstração o envio de fotos fica desligado. No site da sua pousada você envia quantas quiser.']);
        }

        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'caption' => ['nullable', 'string', 'max:120'],
        ]);

        if ($accommodation->photos()->count() >= 30) {
            throw ValidationException::withMessages(['photo' => 'Cada acomodação pode ter até 30 fotos.']);
        }

        $media = $images->store($property, $request->file('photo'));
        $accommodation->photos()->create([
            'path' => $media->path(),
            'caption' => $request->input('caption'),
            'sort_order' => ($accommodation->photos()->max('sort_order') ?? -1) + 1,
        ]);

        return new AdminAccommodationResource($this->load($accommodation));
    }

    /**
     * Legenda e ordem das fotos (a primeira é a capa da acomodação no site).
     */
    public function updatePhotos(Request $request, Accommodation $accommodation): AdminAccommodationResource
    {
        $this->authorizeOwner($request, $accommodation);
        $ids = $accommodation->photos()->pluck('id')->all();

        $data = $request->validate([
            'photos' => ['required', 'array', 'size:'.count($ids)],
            'photos.*.id' => ['required', 'integer', 'distinct', Rule::in($ids)],
            'photos.*.caption' => ['nullable', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['photos'] as $i => $photo) {
                AccommodationPhoto::whereKey($photo['id'])->update(['caption' => $photo['caption'] ?? null, 'sort_order' => $i]);
            }
        });

        return new AdminAccommodationResource($this->load($accommodation));
    }

    public function destroyPhoto(Request $request, Accommodation $accommodation, AccommodationPhoto $photo): AdminAccommodationResource
    {
        $this->authorizeOwner($request, $accommodation);
        abort_unless($photo->accommodation_id === $accommodation->id, 404);

        $mediaId = Media::idFromPath($photo->path);
        $photo->delete();
        $this->deleteUnusedMedia([$mediaId]);

        return new AdminAccommodationResource($this->load($accommodation));
    }

    // ── Temporadas e feriados ──

    public function storeSeason(Request $request, Accommodation $accommodation): AdminAccommodationResource
    {
        $this->authorizeOwner($request, $accommodation);
        $accommodation->seasonalPrices()->create($this->validatedSeason($request));

        return new AdminAccommodationResource($this->load($accommodation));
    }

    public function updateSeason(Request $request, Accommodation $accommodation, SeasonalPrice $season): AdminAccommodationResource
    {
        $this->authorizeOwner($request, $accommodation);
        abort_unless($season->accommodation_id === $accommodation->id, 404);
        $season->update($this->validatedSeason($request));

        return new AdminAccommodationResource($this->load($accommodation));
    }

    public function destroySeason(Request $request, Accommodation $accommodation, SeasonalPrice $season): AdminAccommodationResource
    {
        $this->authorizeOwner($request, $accommodation);
        abort_unless($season->accommodation_id === $accommodation->id, 404);
        $season->delete();

        return new AdminAccommodationResource($this->load($accommodation));
    }

    // ── Apoio ──

    private function authorizeOwner(Request $request, Accommodation $accommodation): Property
    {
        abort_unless($accommodation->property_id === $request->user()->property_id, 404);

        return $request->user()->property;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Property $property, ?Accommodation $accommodation = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:3000'],
            'amenities' => ['nullable', 'array', 'max:30'],
            'amenities.*' => ['nullable', 'string', 'max:80'],
            // Espaços de evento recebem bem mais gente que um quarto.
            'max_guests' => ['required', 'integer', 'min:1', 'max:500'],
            'included_guests' => ['nullable', 'integer', 'min:1', 'lte:max_guests'],
            'base_price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'weekend_price' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'extra_guest_price' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'cleaning_fee' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'weekly_discount' => ['nullable', 'integer', 'min:0', 'max:90'],
            'monthly_discount' => ['nullable', 'integer', 'min:0', 'max:90'],
            'min_nights' => ['required', 'integer', 'min:1', 'max:90'],
            'active' => ['required', 'boolean'],
            'best_for' => ['nullable', Rule::in(['casal', 'familia'])],
            // Por diária ou por hora (day use, eventos), e os horários de cada modo.
            'booking_mode' => ['sometimes', Rule::in([Accommodation::NIGHTLY, Accommodation::HOURLY])],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'hourly_price' => ['nullable', 'required_if:booking_mode,'.Accommodation::HOURLY, 'integer', 'min:0', 'max:100000000'],
            'min_hours' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i', 'after:opens_at'],
            // Pagamento online: formas aceitas, desconto no Pix e prazo da promoção (com loop opcional).
            'accepts_pix' => ['sometimes', 'boolean'],
            'accepts_card' => ['sometimes', 'boolean'],
            'pix_discount' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'promo_ends_at' => ['nullable', 'date'],
            'promo_loop' => ['sometimes', 'boolean'],
            'promo_loop_hours' => ['sometimes', 'integer', Rule::in(self::LOOP_HOURS)],
        ], [
            'pix_discount.max' => 'O desconto no Pix vai até 50%.',
            'promo_loop_hours.in' => 'Escolha de quanto em quanto tempo a promoção recomeça.',
            'hourly_price.required_if' => 'Informe o preço da hora.',
            'closes_at.after' => 'O horário de fechar precisa ser depois do de abrir.',
        ]);

        if (array_key_exists('promo_ends_at', $data)) {
            $data['promo_ends_at'] = $data['promo_ends_at'] ? CarbonImmutable::parse($data['promo_ends_at'])->utc() : null;
        }

        // Linhas em branco somem e repetidas viram uma só.
        $data['amenities'] = array_values(array_unique(array_filter(array_map(fn ($item) => trim((string) $item), $data['amenities'] ?? []), 'strlen')));

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSeason(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'weekend_price' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'min_nights' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);
    }

    /**
     * A capa indica uma acomodação por perfil: escolher outra para "casal" tira a indicação da anterior.
     */
    private function claimBestFor(Property $property, ?string $bestFor, ?Accommodation $except = null): void
    {
        if ($bestFor) {
            $property->accommodations()->where('best_for', $bestFor)
                ->when($except, fn ($query) => $query->whereKeyNot($except->id))
                ->update(['best_for' => null]);
        }
    }

    private function uniqueSlug(Property $property, string $name): string
    {
        $base = Str::slug($name) ?: 'acomodacao';
        $slug = $base;

        for ($i = 2; $property->accommodations()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /**
     * @param  list<int|null>  $ids
     */
    private function deleteUnusedMedia(array $ids): void
    {
        foreach (array_filter($ids) as $id) {
            if (! AccommodationPhoto::where('path', Media::PATH_PREFIX.$id)->exists()) {
                Media::whereKey($id)->delete();
            }
        }
    }

    /**
     * @template T of Accommodation|\Illuminate\Support\Collection<int, Accommodation>
     *
     * @param  T  $accommodations
     * @return T
     */
    private function load($accommodations)
    {
        return $accommodations->load(['photos', 'seasonalPrices' => fn ($query) => $query->orderBy('starts_on')])
            ->loadCount([
                'bookings as bookings_count',
                'bookings as upcoming_bookings_count' => fn ($query) => $query
                    ->holdingDates()
                    ->whereDate('check_out', '>', now()->toDateString()),
            ]);
    }
}
