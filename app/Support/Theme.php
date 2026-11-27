<?php

namespace App\Support;

use App\Models\User;

/**
 * Cores do app que a pessoa pode escolher (Configurações > Perfil). Cada tema troca só os tons da marca;
 * vermelho (saída), verde (entrada) e amarelo (alerta) continuam com o significado de sempre.
 * Todos têm contraste de pelo menos 4,5:1 do texto branco sobre a cor principal.
 */
final class Theme
{
    public const DEFAULT = 'verde';

    /** @var array<string, array{label: string, brand: string, dark: string, mid: string, light: string, soft: string, accent: string}> */
    public const PALETTES = [
        'verde' => ['label' => 'Verde', 'brand' => '#14614F', 'dark' => '#0E3B31', 'mid' => '#1B7A63', 'light' => '#5BB89A', 'soft' => '#E9F5F0', 'accent' => '#C8F169'],
        'azul' => ['label' => 'Azul', 'brand' => '#1D5FA8', 'dark' => '#123B6D', 'mid' => '#2A7BCB', 'light' => '#7DB3E8', 'soft' => '#E8F0FA', 'accent' => '#BFE3FF'],
        'roxo' => ['label' => 'Roxo', 'brand' => '#6B3FA0', 'dark' => '#43266B', 'mid' => '#8656C0', 'light' => '#B497DB', 'soft' => '#F1EBF8', 'accent' => '#E3D0FF'],
        'rosa' => ['label' => 'Rosa', 'brand' => '#B83280', 'dark' => '#7A1F55', 'mid' => '#D14A99', 'light' => '#F08CC2', 'soft' => '#FCE9F4', 'accent' => '#FFD1EA'],
        'laranja' => ['label' => 'Laranja', 'brand' => '#B04F00', 'dark' => '#733400', 'mid' => '#D9731A', 'light' => '#F5A862', 'soft' => '#FDF0E2', 'accent' => '#FFE2B8'],
        'turquesa' => ['label' => 'Turquesa', 'brand' => '#0F6E78', 'dark' => '#0A474E', 'mid' => '#178C98', 'light' => '#6CC5CE', 'soft' => '#E4F4F5', 'accent' => '#BDF2F5'],
        'grafite' => ['label' => 'Grafite', 'brand' => '#3F4A52', 'dark' => '#232A2F', 'mid' => '#566470', 'light' => '#98A6B1', 'soft' => '#EDF0F2', 'accent' => '#D5E1EA'],
    ];

    public static function valid(?string $key): bool
    {
        return $key !== null && isset(self::PALETTES[$key]);
    }

    /** Chave do tema da pessoa logada; sem login, a última escolha lembrada no navegador; senão o padrão. */
    public static function key(?User $user = null): string
    {
        $key = $user?->theme_color ?? request()->cookie('nf_theme');

        return self::valid($key) ? $key : self::DEFAULT;
    }

    /** @return array{label: string, brand: string, dark: string, mid: string, light: string, soft: string, accent: string} */
    public static function palette(?string $key): array
    {
        return self::PALETTES[self::valid($key) ? $key : self::DEFAULT];
    }

    /** CSS que troca as cores da marca (vai no <head>, depois do CSS do app). */
    public static function css(?string $key): string
    {
        $p = self::palette($key);

        return ":root{--color-brand:{$p['brand']};--color-brand-dark:{$p['dark']};--color-brand-mid:{$p['mid']};--color-brand-light:{$p['light']};--color-brand-soft:{$p['soft']};--color-lime:{$p['accent']};}";
    }
}
