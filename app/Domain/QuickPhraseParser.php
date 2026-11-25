<?php

namespace App\Domain;

use App\Support\Money;
use InvalidArgumentException;

/**
 * Frase rápida (RF-05): "50 mercado" vira saída de R$ 50,00 chamada "mercado",
 * na categoria "Outros". Frase fora do formato é rejeitada e nada é gravado.
 */
final class QuickPhraseParser
{
    public const DEFAULT_CATEGORY = 'Outros';

    public static function parse(string $phrase): QuickEntry
    {
        $text = trim(preg_replace('/\s+/u', ' ', $phrase) ?? '');

        if (! preg_match('/^(?<valor>\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?|\d+(?:,\d{1,2})?)\s+(?<nome>\S.*)$/u', $text, $m)) {
            throw new InvalidQuickPhrase('Use o formato "valor nome", por exemplo "50 mercado".');
        }

        try {
            $amount = Money::parse($m['valor']);
        } catch (InvalidArgumentException) {
            throw new InvalidQuickPhrase('Valor inválido na frase rápida.');
        }

        if ($amount <= 0) {
            throw new InvalidQuickPhrase('O valor precisa ser maior que zero.');
        }

        return new QuickEntry('saida', $amount, $m['nome'], self::DEFAULT_CATEGORY);
    }
}
