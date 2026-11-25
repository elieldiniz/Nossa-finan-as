<?php

use App\Domain\InvalidQuickPhrase;
use App\Domain\QuickPhraseParser;

it('entende frases válidas', function (string $phrase, int $amount, string $name) {
    $entry = QuickPhraseParser::parse($phrase);

    expect($entry->amount)->toBe($amount)
        ->and($entry->name)->toBe($name)
        ->and($entry->type)->toBe('saida')
        ->and($entry->category)->toBe('Outros');
})->with([
    ['50 mercado', 5000, 'mercado'],
    ['12,50 pão', 1250, 'pão'],
    ['  1.234,56   conta de luz ', 123456, 'conta de luz'],
]);

it('rejeita frases fora do formato', function (string $phrase) {
    QuickPhraseParser::parse($phrase);
})->with(['mercado', '0 x', '', '50', 'R$ 50 mercado', '-5 padaria'])->throws(InvalidQuickPhrase::class);
