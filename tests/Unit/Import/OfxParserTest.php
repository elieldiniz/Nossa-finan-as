<?php

use App\Import\InvalidImportFile;
use App\Import\OfxCategorizer;
use App\Import\OfxParser;

function ofx(): array
{
    return (new OfxParser)->parse(file_get_contents(__DIR__.'/../../Fixtures/ofx_nubank.ofx'));
}

it('lê banco, período e as transações da fatura', function () {
    $o = ofx();

    expect($o['bank'])->toBe('NU PAGAMENTOS S.A.')->and($o['start'])->toBe('2026-10-31')->and($o['end'])->toBe('2026-11-30')
        ->and($o['transactions'])->toHaveCount(7)
        ->and($o['transactions'][0])->toMatchArray(['id' => 'fitid-0001:4516', 'date' => '2026-11-22', 'amount' => -4516, 'name' => 'Restaurante Fogao A Le', 'installment' => null]);
});

it('reconhece "Parcela k/n" e tira da descrição', function () {
    $t = ofx()['transactions'];

    expect($t[1])->toMatchArray(['name' => 'Havan Ji Parana', 'installment' => [1, 2]])
        ->and($t[2])->toMatchArray(['name' => 'Jim.Com* Gp Motos', 'installment' => [4, 4]]);
});

it('créditos têm valor positivo (pagamento da fatura e estornos)', function () {
    $t = ofx()['transactions'];

    expect($t[5]['amount'])->toBe(102559)->and($t[6]['amount'])->toBe(503)->and($t[6]['name'])->toContain('Google One'); // acento ok (UTF-8)
});

it('arquivo que não é OFX ou sem transações dá erro claro', function () {
    expect(fn () => (new OfxParser)->parse('nada a ver'))->toThrow(InvalidImportFile::class, 'OFX');
    expect(fn () => (new OfxParser)->parse('<OFX><BANKTRANLIST></BANKTRANLIST></OFX>'))->toThrow(InvalidImportFile::class, 'nenhuma transação');
});

it('texto em CP1252 (não UTF-8) também fica com acento certo', function () {
    $conteudo = mb_convert_encoding('<OFX><STMTTRN><DTPOSTED>20261101</DTPOSTED><TRNAMT>-10.00</TRNAMT><FITID>a</FITID><MEMO>Pão de Açúcar</MEMO></STMTTRN></OFX>', 'Windows-1252', 'UTF-8');

    expect((new OfxParser)->parse($conteudo)['transactions'][0]['name'])->toBe('Pão de Açúcar');
});

it('sugere categoria pelo nome do estabelecimento', function () {
    expect(OfxCategorizer::suggest('Jeeda Supermercados'))->toBe('Mercado')
        ->and(OfxCategorizer::suggest('Dl*Spotify P4949'))->toBe('Assinatura')
        ->and(OfxCategorizer::suggest('Auto Posto Mourao'))->toBe('Transporte')
        ->and(OfxCategorizer::suggest('Rd Farma'))->toBe('Saúde')
        ->and(OfxCategorizer::suggest('Loja M3'))->toBe('Outros');
});

it('o banco repete o identificador da compra e do IOF: cada linha continua única', function () {
    $conteudo = '<OFX><STMTTRN><DTPOSTED>20261108</DTPOSTED><TRNAMT>-4.00</TRNAMT><FITID>mesmo</FITID><MEMO>IOF de compra internacional</MEMO></STMTTRN>'
        .'<STMTTRN><DTPOSTED>20261108</DTPOSTED><TRNAMT>-114.40</TRNAMT><FITID>mesmo</FITID><MEMO>Anthropic* Claude Sub</MEMO></STMTTRN></OFX>';

    $ids = array_column((new OfxParser)->parse($conteudo)['transactions'], 'id');

    expect($ids)->toBe(['mesmo:400', 'mesmo:11440'])->and(array_unique($ids))->toHaveCount(2);
});
