<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Property;
use App\Services\DemoService;
use App\Services\ImageService;
use App\Support\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Seu site" no painel: identidade (nome, cores, contato, logo e ícone), textos e SEO de cada
 * página e SEO avançado. Toda resposta devolve a configuração inteira, já como o site vai usar.
 */
class SiteController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->respond($request->user()->property);
    }

    public function updateIdentity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:1200'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            // Só números, com o código do país: é o que o link wa.me aceita.
            'whatsapp' => ['nullable', 'string', 'regex:/^\d{10,15}$/'],
            'address' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'size:2'],
        ], [
            'whatsapp.regex' => 'Use só números, com o código do país e o DDD. Exemplo: 5511999990000.',
            'primary_color.regex' => 'Escolha uma cor válida.',
        ]);

        $data['state'] = isset($data['state']) ? strtoupper($data['state']) : null;
        $data['primary_color'] = strtolower($data['primary_color']);
        $property = $request->user()->property;
        $property->update($data);

        return $this->respond($property);
    }

    public function updatePage(Request $request, string $page): JsonResponse
    {
        abort_unless(in_array($page, SiteSettings::PAGES, true), 404);

        $rules = ['menu' => ['sometimes', 'boolean'], 'index' => ['sometimes', 'boolean']];
        foreach (SiteSettings::LIMITS as $field => $max) {
            $rules[$field] = ['nullable', 'string', "max:{$max}"];
        }
        if ($page === 'acomodacoes') {
            $rules['photos'] = ['sometimes', Rule::in(SiteSettings::PHOTO_LAYOUTS)];
        }
        $data = $request->validate($rules);
        if (isset($data['keywords'])) {
            $data['keywords'] = self::keywords($data['keywords']);
        }

        $property = $request->user()->property;
        $settings = $property->settings ?? [];
        $pages = SiteSettings::pages($settings['pages'] ?? null);
        $pages[$page] = SiteSettings::pages([$page => [...$pages[$page], ...$data]])[$page];
        $property->update(['settings' => ['pages' => $pages] + $settings]);

        return $this->respond($property);
    }

    public function updateSeo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:'.SiteSettings::LIMITS['seo_description']],
            'keywords' => ['nullable', 'string', 'max:'.SiteSettings::LIMITS['keywords']],
            'author' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'indexable' => ['required', 'boolean'],
            'separator' => ['sometimes', Rule::in(SiteSettings::SEPARATORS)],
            'ai_bots' => ['sometimes', Rule::in(SiteSettings::AI_BOTS)],
            'structured_data' => ['sometimes', 'boolean'],
            'schema_type' => ['sometimes', Rule::in(SiteSettings::SCHEMA_TYPES)],
            'sitemap_images' => ['sometimes', 'boolean'],
            // Só o código: o painel aceita a tag inteira e extrai o content antes de enviar.
            'google_verification' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'ga_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/'],
            'meta_pixel_id' => ['nullable', 'string', 'regex:/^\d{6,20}$/'],
        ], [
            'google_verification.regex' => 'Cole só o código de verificação do Google (letras, números, - e _).',
            'ga_id.regex' => 'O ID do Google Analytics começa com G-, como G-ABC123XYZ.',
            'meta_pixel_id.regex' => 'O ID do Pixel do Meta tem só números.',
            'latitude.required_with' => 'Informe a latitude e a longitude juntas.',
            'longitude.required_with' => 'Informe a latitude e a longitude juntas.',
        ]);
        if (isset($data['keywords'])) {
            $data['keywords'] = self::keywords($data['keywords']);
        }

        $property = $request->user()->property;
        $settings = $property->settings ?? [];
        // Campos que não vieram ficam como estavam.
        $seo = SiteSettings::seo([...SiteSettings::seo($settings['seo'] ?? null), ...$data]);
        $property->update(['settings' => ['seo' => $seo] + $settings]);

        return $this->respond($property);
    }

    /**
     * Redes sociais do rodapé. Cada campo aceita o link do perfil ou o @usuário; vazio tira a rede.
     */
    public function updateSocial(Request $request): JsonResponse
    {
        $rules = [];
        foreach (array_keys(SiteSettings::SOCIAL) as $network) {
            $rules[$network] = ['nullable', 'string', 'max:300'];
        }
        $data = $request->validate($rules);

        $social = [];
        $errors = [];
        foreach ($data as $network => $value) {
            if (blank($value)) {
                continue;
            }
            $url = SiteSettings::socialUrl($network, $value);
            if ($url === null) {
                $errors[$network] = self::SOCIAL_HINTS[$network];
            } else {
                $social[$network] = $url;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $property = $request->user()->property;
        $settings = $property->settings ?? [];
        unset($settings['social']);
        $property->update(['settings' => ($social ? ['social' => $social] : []) + $settings ?: null]);

        return $this->respond($property);
    }

    /**
     * Temas e layouts próprios ainda vão chegar: o dono pede para ser avisado (e diz o que quer).
     */
    public function notifyThemes(Request $request): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $property = $request->user()->property;
        $property->update(['settings' => ['themes_waitlist' => ['at' => now()->toAtomString(), 'note' => $data['note'] ?? null]] + ($property->settings ?? [])]);

        return $this->respond($property);
    }

    /** Mensagem quando o link não é da rede. */
    private const SOCIAL_HINTS = [
        'instagram' => 'Cole o link do perfil (instagram.com/...) ou o @ da pousada.',
        'facebook' => 'Cole o link da página (facebook.com/...).',
        'tiktok' => 'Cole o link do perfil (tiktok.com/@...) ou o @.',
        'youtube' => 'Cole o link do canal (youtube.com/@...) ou o @.',
        'x' => 'Cole o link do perfil (x.com/...) ou o @.',
        'tripadvisor' => 'Cole o link da página da pousada no Tripadvisor.',
        'google' => 'Cole o link da pousada no Google Maps (o de "Compartilhar").',
        'airbnb' => 'Cole o link do anúncio ou do perfil no Airbnb.',
        'booking' => 'Cole o link da página da pousada no Booking.com.',
    ];

    /** "pousada, Mairiporã ,  chalé,pousada" → "pousada, Mairiporã, chalé": sem vazios nem repetidas. */
    private static function keywords(?string $value): ?string
    {
        $words = collect(explode(',', (string) $value))->map(fn ($w) => trim(preg_replace('/\s+/', ' ', $w)))->filter()
            ->unique(fn ($w) => mb_strtolower($w))->values();

        return $words->isEmpty() ? null : $words->join(', ');
    }

    public function storeImage(Request $request, string $kind, ImageService $images, DemoService $demo): JsonResponse
    {
        abort_unless(in_array($kind, SiteSettings::IMAGES, true), 404);
        $property = $request->user()->property;

        // Na demonstração o site é público: uma imagem enviada por um visitante apareceria para todos.
        if ($demo->isDemo($property)) {
            throw ValidationException::withMessages(['photo' => 'Na demonstração o envio de imagens fica desligado. No site da sua pousada você troca quando quiser.']);
        }

        $request->validate(['photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:12288']]);

        $media = $images->storeSiteImage($property, $request->file('photo'), $kind);
        $this->replaceImage($property, $kind, $media->path());

        return $this->respond($property);
    }

    public function destroyImage(Request $request, string $kind): JsonResponse
    {
        abort_unless(in_array($kind, SiteSettings::IMAGES, true), 404);
        $property = $request->user()->property;
        $this->replaceImage($property, $kind, null);

        return $this->respond($property);
    }

    /** Troca o caminho salvo e apaga do banco a imagem anterior, se foi enviada pelo painel. */
    private function replaceImage(Property $property, string $kind, ?string $path): void
    {
        $old = SiteSettings::imagePath($property, $kind);

        if ($kind === 'logo') {
            $property->update(['logo_path' => $path]);
        } else {
            $settings = $property->settings ?? [];
            unset($settings[$kind.'_path']);
            $property->update(['settings' => ($path ? [$kind.'_path' => $path] : []) + $settings ?: null]);
        }

        if ($id = Media::idFromPath($old)) {
            Media::whereKey($id)->where('property_id', $property->id)->delete();
        }
    }

    private function respond(Property $property): JsonResponse
    {
        $settings = $property->settings ?? [];

        return response()->json([
            'identity' => [
                ...$property->only(SiteSettings::IDENTITY),
                ...SiteSettings::imageUrls($property),
            ],
            'pages' => SiteSettings::pages($settings['pages'] ?? null),
            'seo' => SiteSettings::seo($settings['seo'] ?? null),
            'social' => SiteSettings::social($settings['social'] ?? null),
            'themes_waitlist' => isset($settings['themes_waitlist']),
            'limits' => SiteSettings::LIMITS,
        ]);
    }
}
