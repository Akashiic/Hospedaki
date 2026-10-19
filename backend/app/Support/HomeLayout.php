<?php

namespace App\Support;

/**
 * Seções da página inicial e a ordem em que cada pousada quer mostrá-las.
 * Fica em properties.settings.home_sections como [{key, visible}].
 *
 * A capa é sempre a primeira e não some: é a porta de entrada do site.
 */
final class HomeLayout
{
    /** Todas as seções, na ordem padrão. */
    public const SECTIONS = ['hero', 'story', 'stays', 'intro', 'gallery', 'extras'];

    /** Visíveis quando a pousada ainda não escolheu: a home de sempre (capa, "Um dia aqui" e acomodações). */
    public const DEFAULT_VISIBLE = ['hero', 'story', 'stays'];

    public const LOCKED = 'hero';

    /**
     * Lista completa e válida a partir do que foi salvo: ignora chaves desconhecidas e repetidas,
     * põe a capa no topo e acrescenta no fim, escondidas, as seções que a lista salva não tinha
     * (as que forem criadas depois não aparecem sozinhas no site de quem já arrumou a home).
     *
     * @param  array<int, mixed>|null  $saved
     * @return list<array{key: string, visible: bool}>
     */
    public static function normalize(?array $saved): array
    {
        if ($saved === null) {
            return array_map(fn (string $key) => ['key' => $key, 'visible' => in_array($key, self::DEFAULT_VISIBLE, true)], self::SECTIONS);
        }

        $sections = [];
        foreach ($saved as $item) {
            $key = is_array($item) ? ($item['key'] ?? null) : null;
            if (in_array($key, self::SECTIONS, true) && ! isset($sections[$key])) {
                $sections[$key] = ['key' => $key, 'visible' => (bool) ($item['visible'] ?? false)];
            }
        }

        foreach (self::SECTIONS as $key) {
            $sections[$key] ??= ['key' => $key, 'visible' => false];
        }

        $hero = ['key' => self::LOCKED, 'visible' => true];
        unset($sections[self::LOCKED]);

        return [$hero, ...array_values($sections)];
    }
}
