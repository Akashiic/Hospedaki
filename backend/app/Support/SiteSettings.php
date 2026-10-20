<?php

namespace App\Support;

use App\Http\Resources\PhotoUrl;
use App\Models\Property;

/**
 * Configurações do site de cada pousada, editadas no painel em "Seu site":
 * textos e SEO de cada página, ícone, imagem de compartilhamento e SEO avançado.
 * Ficam em properties.settings (pages, seo, icon_path); o logo usa a coluna logo_path.
 *
 * Campo vazio (null) quer dizer "usar o padrão": o site monta o texto com o nome da pousada.
 */
final class SiteSettings
{
    /** Páginas do site, na ordem do menu. */
    public const PAGES = ['inicio', 'lugar', 'acomodacoes', 'reservar', 'contato'];

    /** Páginas que o dono pode tirar do menu (Início e Reservar ficam sempre: são a porta e o objetivo). */
    public const OPTIONAL_IN_MENU = ['lugar', 'acomodacoes', 'contato'];

    /** Páginas que o dono pode tirar do Google (o Início segue a opção do site inteiro, em SEO). */
    public const OPTIONAL_IN_SEARCH = ['lugar', 'acomodacoes', 'reservar', 'contato'];

    /** Separadores do título na aba e no Google ("O lugar · Pousada"). */
    public const SEPARATORS = ['·', '|', '–', '—'];

    /**
     * Robôs de inteligência artificial no robots.txt: liberados, só os que treinam modelos
     * bloqueados, ou todos (inclusive os de busca com IA, como o do ChatGPT).
     */
    public const AI_BOTS = ['allow', 'training', 'all'];

    /** Tipos de hospedagem do Schema.org que o Google entende (dados estruturados). */
    public const SCHEMA_TYPES = ['BedAndBreakfast', 'LodgingBusiness', 'Hotel', 'Hostel', 'Resort', 'Campground', 'Motel'];

    /** Páginas com abertura própria (título sobre a foto); a do início é a capa, editada nas seções. */
    public const WITH_HEADER = ['lugar', 'acomodacoes', 'reservar', 'contato'];

    /** Limites dos textos, os mesmos que o painel mostra. */
    public const LIMITS = ['eyebrow' => 40, 'title' => 80, 'lead' => 240, 'seo_title' => 70, 'seo_description' => 170];

    /** Campos da pousada que o dono muda em "Configurações". */
    public const IDENTITY = ['name', 'tagline', 'description', 'primary_color', 'email', 'phone', 'whatsapp', 'address', 'city', 'state'];

    /** Imagens do site: logo (menu), ícone (aba do navegador) e imagem de compartilhamento (WhatsApp, redes). */
    public const IMAGES = ['logo', 'icon', 'share'];

    /**
     * Configuração de cada página, completa: chaves desconhecidas são ignoradas e as que faltam
     * entram com o padrão.
     *
     * @return array<string, array{menu: bool, index: bool, eyebrow: ?string, title: ?string, lead: ?string, seo_title: ?string, seo_description: ?string}>
     */
    public static function pages(?array $saved): array
    {
        $pages = [];
        foreach (self::PAGES as $key) {
            $item = is_array($saved[$key] ?? null) ? $saved[$key] : [];
            $page = [
                'menu' => in_array($key, self::OPTIONAL_IN_MENU, true) ? (bool) ($item['menu'] ?? true) : true,
                'index' => in_array($key, self::OPTIONAL_IN_SEARCH, true) ? (bool) ($item['index'] ?? true) : true,
            ];
            foreach (array_keys(self::LIMITS) as $field) {
                $page[$field] = self::text($item[$field] ?? null);
            }
            $pages[$key] = $page;
        }

        return $pages;
    }

    /**
     * SEO avançado da pousada, com os padrões.
     *
     * @return array{description: ?string, indexable: bool, separator: string, ai_bots: string, structured_data: bool, schema_type: string, sitemap_images: bool, google_verification: ?string, ga_id: ?string, meta_pixel_id: ?string}
     */
    public static function seo(?array $saved): array
    {
        return [
            'description' => self::text($saved['description'] ?? null),
            'indexable' => (bool) ($saved['indexable'] ?? true),
            'separator' => self::oneOf($saved['separator'] ?? null, self::SEPARATORS),
            'ai_bots' => self::oneOf($saved['ai_bots'] ?? null, self::AI_BOTS),
            'structured_data' => (bool) ($saved['structured_data'] ?? true),
            'schema_type' => self::oneOf($saved['schema_type'] ?? null, self::SCHEMA_TYPES),
            'sitemap_images' => (bool) ($saved['sitemap_images'] ?? true),
            'google_verification' => self::text($saved['google_verification'] ?? null),
            'ga_id' => self::text($saved['ga_id'] ?? null),
            'meta_pixel_id' => self::text($saved['meta_pixel_id'] ?? null),
        ];
    }

    /** Caminho salvo de cada imagem do site ("media:12", "/fotos/x.jpg" ou null). */
    public static function imagePath(Property $property, string $kind): ?string
    {
        return $kind === 'logo' ? $property->logo_path : ($property->settings[$kind.'_path'] ?? null);
    }

    /** @return array{logo_url: ?string, icon_url: ?string, share_image_url: ?string} */
    public static function imageUrls(Property $property): array
    {
        return [
            'logo_url' => PhotoUrl::from(self::imagePath($property, 'logo')),
            'icon_url' => PhotoUrl::from(self::imagePath($property, 'icon')),
            'share_image_url' => PhotoUrl::from(self::imagePath($property, 'share')),
        ];
    }

    /** O valor salvo, se for uma das opções; senão a primeira (o padrão). */
    private static function oneOf(mixed $value, array $options): string
    {
        return in_array($value, $options, true) ? $value : $options[0];
    }

    /** Texto aparado; vazio vira null (padrão). */
    private static function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
