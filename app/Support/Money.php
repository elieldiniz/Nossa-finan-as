<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Valores monetários sempre em centavos inteiros (RNF-05). Nunca usa float.
 */
final class Money
{
    private const MINUS = "\u{2212}"; // sinal de menos tipográfico (−)

    /**
     * Converte texto em centavos. Aceita "1.234,56", "12,50", "50", "R$ 2.250,00" e "-3".
     * Floats são rejeitados de propósito, para não perder precisão.
     */
    public static function parse(string|int $value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        $text = trim(str_replace(['R$', ' ', "\u{00A0}", self::MINUS], ['', '', '', '-'], $value));

        if ($text === '' || ! preg_match('/^-?\d{1,3}(\.\d{3})*(,\d{1,2})?$|^-?\d+(,\d{1,2})?$/', $text)) {
            throw new InvalidArgumentException("Valor monetário inválido: \"{$value}\"");
        }

        $negative = str_starts_with($text, '-');
        $text = ltrim($text, '-');
        $text = str_replace('.', '', $text);

        [$reais, $centavos] = array_pad(explode(',', $text, 2), 2, '0');
        $centavos = str_pad($centavos, 2, '0');

        $cents = ((int) $reais) * 100 + (int) $centavos;

        return $negative ? -$cents : $cents;
    }

    /** Formata centavos como "R$ 2.250,00"; negativo vira "−R$ 485,00". */
    public static function format(int $cents): string
    {
        $abs = abs($cents);
        $body = number_format(intdiv($abs, 100), 0, '', '.').','.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return ($cents < 0 ? self::MINUS : '').'R$ '.$body;
    }

    /** Formata com sinal explícito: "+R$ 115,00" / "−R$ 485,00" / "R$ 0,00". */
    public static function signed(int $cents): string
    {
        return ($cents > 0 ? '+' : '').self::format($cents);
    }

    /** Porcentagem com 2 casas ("50,00%"), só com inteiros. Total 0 devolve "0,00%". */
    public static function percent(int $part, int $total): string
    {
        if ($total === 0) {
            return '0,00%';
        }

        $negative = ($part < 0) !== ($total < 0) && $part !== 0;
        $basis = intdiv(abs($part) * 10000 * 2 + abs($total), abs($total) * 2); // arredonda para o mais próximo

        $text = intdiv($basis, 100).','.str_pad((string) ($basis % 100), 2, '0', STR_PAD_LEFT).'%';

        return ($negative ? self::MINUS : '').$text;
    }

    /** Divide centavos em partes que somam exatamente o total (resto vai para as primeiras). */
    public static function split(int $cents, int $parts): array
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('O número de partes deve ser pelo menos 1.');
        }

        $base = intdiv($cents, $parts);
        $rest = $cents - $base * $parts;
        $result = [];

        for ($i = 0; $i < $parts; $i++) {
            $result[] = $base + ($i < $rest ? 1 : 0);
        }

        return $result;
    }
}
