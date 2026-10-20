<?php

namespace App\Http\Resources;

use App\Models\Property;
use App\Services\DemoService;
use App\Support\HomeLayout;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Property */
class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'tagline' => $this->tagline,
            'slug' => $this->slug,
            'description' => $this->description,
            'highlights' => $this->highlights ?? [],
            'story' => $this->story ?? [],
            'story_title' => $this->story_title,
            'extras' => $this->extras ?? [],
            // Seções da página inicial na ordem escolhida pelo dono (a capa sempre primeiro).
            'home_sections' => HomeLayout::normalize($this->settings['home_sections'] ?? null),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'url' => PhotoUrl::from($photo->path),
                'caption' => $photo->caption,
                'featured' => $photo->featured,
            ])),
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            // Logo do menu, ícone da aba do navegador e imagem que aparece ao compartilhar o link.
            ...SiteSettings::imageUrls($this->resource),
            // Textos, SEO e presença no menu de cada página, escolhidos em "Seu site" no painel.
            'pages' => SiteSettings::pages($this->settings['pages'] ?? null),
            'seo' => SiteSettings::seo($this->settings['seo'] ?? null),
            // Perfis da pousada nas redes (rodapé e sameAs dos dados estruturados).
            'social' => SiteSettings::social($this->settings['social'] ?? null),
            // Domínio próprio já no ar (o site usa no endereço canônico e no mapa do site).
            'domain' => ($this->settings['domain']['status'] ?? null) === 'active' ? $this->domain : null,
            'primary_color' => $this->primary_color,
            'check_in_time' => substr((string) $this->check_in_time, 0, 5),
            'check_out_time' => substr((string) $this->check_out_time, 0, 5),
            // O site calcula "hoje" neste fuso, o mesmo que a API usa para validar as datas.
            'timezone' => $this->timezone ?: Property::DEFAULT_TIMEZONE,
            // Pousada de demonstração: o site mostra o aviso de demo e o login aberto do painel.
            'demo' => app(DemoService::class)->isDemo($this->resource),
            'accommodations' => AccommodationResource::collection($this->whenLoaded('accommodations')),
        ];
    }
}
