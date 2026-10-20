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
    public const LIMITS = ['eyebrow' => 40, 'title' => 80, 'lead' => 240, 'seo_title' => 70, 'seo_description' => 170, 'keywords' => 255];

    /** Fotos na página de cada acomodação: em grade ou em carrossel. */
    public const PHOTO_LAYOUTS = ['grid', 'carousel'];

    /**
     * Redes sociais do rodapé, na ordem em que aparecem, com o endereço de cada perfil.
     * O dono pode colar o link inteiro ou só o @usuário (nas redes que usam @).
     */
    public const SOCIAL = [
        'instagram' => ['host' => 'instagram.com', 'handle' => 'https://www.instagram.com/%s/'],
        'facebook' => ['host' => 'facebook.com', 'handle' => 'https://www.facebook.com/%s'],
        'tiktok' => ['host' => 'tiktok.com', 'handle' => 'https://www.tiktok.com/@%s'],
        'youtube' => ['host' => 'youtube.com', 'handle' => 'https://www.youtube.com/@%s'],
        'x' => ['host' => 'x.com', 'handle' => 'https://x.com/%s'],
        'tripadvisor' => ['host' => 'tripadvisor.com', 'handle' => null],
        'google' => ['host' => 'google.com', 'handle' => null],
        'airbnb' => ['host' => 'airbnb.com', 'handle' => null],
        'booking' => ['host' => 'booking.com', 'handle' => null],
    ];

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
            // Só a página de acomodações escolhe como as fotos de cada uma aparecem.
            if ($key === 'acomodacoes') {
                $page['photos'] = self::oneOf($item['photos'] ?? null, self::PHOTO_LAYOUTS);
            }
            $pages[$key] = $page;
        }

        return $pages;
    }

    /**
     * SEO avançado da pousada, com os padrões.
     *
     * Além do básico, as metas "clássicas": palavras-chave, autor e a posição da pousada no mapa
     * (geo.position/ICBM, também usada nos dados estruturados).
     *
     * @return array{description: ?string, keywords: ?string, author: ?string, latitude: ?float, longitude: ?float, indexable: bool, separator: string, ai_bots: string, structured_data: bool, schema_type: string, sitemap_images: bool, google_verification: ?string, ga_id: ?string, meta_pixel_id: ?string}
     */
    public static function seo(?array $saved): array
    {
        return [
            'description' => self::text($saved['description'] ?? null),
            'keywords' => self::text($saved['keywords'] ?? null),
            'author' => self::text($saved['author'] ?? null),
            'latitude' => is_numeric($saved['latitude'] ?? null) ? (float) $saved['latitude'] : null,
            'longitude' => is_numeric($saved['longitude'] ?? null) ? (float) $saved['longitude'] : null,
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

    /**
     * Redes sociais preenchidas, na ordem do rodapé: [rede => endereço do perfil].
     *
     * @return array<string, string>
     */
    public static function social(?array $saved): array
    {
        $links = [];
        foreach (array_keys(self::SOCIAL) as $network) {
            if ($url = self::text($saved[$network] ?? null)) {
                $links[$network] = $url;
            }
        }

        return $links;
    }

    /**
     * Endereço do perfil a partir do que o dono colou: o link (com ou sem https://) ou o @usuário.
     * Devolve null quando não é um endereço daquela rede.
     */
    public static function socialUrl(string $network, string $value): ?string
    {
        $value = trim($value);
        $info = self::SOCIAL[$network];

        // Só o usuário (com ou sem @): monta o endereço da rede.
        if ($info['handle'] && preg_match('/^@?([A-Za-z0-9_.\-]{1,60})$/', $value, $m)) {
            return sprintf($info['handle'], $m[1]);
        }

        $url = preg_match('#^https?://#i', $value) ? $value : 'https://'.$value;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        // O domínio da rede, inclusive os de país (tripadvisor.com.br, google.com.br, airbnb.com.br),
        // e os atalhos de compartilhamento do Google Maps e o antigo twitter.com.
        $names = [explode('.', $info['host'])[0], ...($network === 'x' ? ['twitter'] : [])];
        $official = collect($names)->contains(fn ($name) => preg_match('/^(?:[a-z0-9-]+\.)*'.preg_quote($name, '/').'\.(?:com|com\.[a-z]{2}|co\.[a-z]{2}|[a-z]{2})$/', $host));
        $shortcut = $network === 'google' && in_array($host, ['g.page', 'goo.gl', 'maps.app.goo.gl', 'share.google'], true);
        if (! ($official || $shortcut) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return preg_replace('#^http://#i', 'https://', $url);
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
