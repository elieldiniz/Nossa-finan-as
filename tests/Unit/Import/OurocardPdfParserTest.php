<?php

use App\Import\InvalidImportFile;
use App\Import\OurocardPdfParser;

function fatura(): array
{
    return (new OurocardPdfParser)->parseText(file_get_contents(__DIR__.'/../../Fixtures/ourocard_texto.txt'));
}

it('lê vencimento, fechamento, limite, final do cartão e total da fatura', function () {
    $f = fatura();

    expect($f['bank'])->toContain('Ourocard')->and($f['account'])->toBe('1111')->and($f['due_date'])->toBe('2026-12-06')
        ->and($f['closing_date'])->toBe('2026-11-25')->and($f['end'])->toBe('2026-11-25')->and($f['limit'])->toBe(500000)->and($f['total'])->toBe(40000);
});

it('separa as compras dos pagamentos e confere com o total da fatura', function () {
    $t = fatura()['transactions'];
    $compras = array_filter($t, fn ($x) => $x['amount'] < 0);
    $pagamentos = array_filter($t, fn ($x) => $x['amount'] > 0);

    expect(count($t))->toBe(7)->and(count($compras))->toBe(6)->and(count($pagamentos))->toBe(1)
        ->and(-array_sum(array_column($compras, 'amount')))->toBe(40000)   // = total da fatura
        ->and(array_sum(array_column($pagamentos, 'amount')))->toBe(10000);
});

it('lê data (com o ano pelo fechamento), nome sem a cidade e valor', function () {
    $t = collect(fatura()['transactions'])->keyBy('name');

    expect($t['MERCADO BOM'])->toMatchArray(['date' => '2026-10-28', 'amount' => -5000, 'installment' => null])
        ->and($t['LOJA X *LOJA.COM'])->toMatchArray(['date' => '2026-11-02', 'amount' => -3000])
        ->and(array_keys($t->all()))->not->toContain('MERCADO BOM CIDADE');
});

it('parcelas "PARC 02/05": tira da descrição e guarda a parcela; a data é a da compra original', function () {
    $g = collect(fatura()['transactions'])->firstWhere('name', 'GELADEIRA');

    expect($g)->toMatchArray(['installment' => [2, 5], 'date' => '2026-09-07', 'amount' => -12000]);
});

it('cada compra sabe de qual cartão é (principal e adicional) e o subtotal de cada um bate', function () {
    $f = fatura();
    $por = collect($f['transactions'])->where('amount', '<', 0)->groupBy('holder')->map(fn ($g) => -$g->sum('amount'));

    expect($por->all())->toBe(['1111' => 20000, '2222' => 20000])
        ->and($f['holders'])->toBe([['subtotal' => 20000, 'card' => '1111', 'name' => 'MARIA EXEMPLO SILVA'], ['subtotal' => 20000, 'card' => '2222', 'name' => 'JOAO EXEMPLO SILVA']]);
});

it('a categoria do banco (Hospitais) vem junto; "Compras diversas" não vira categoria', function () {
    $t = collect(fatura()['transactions'])->keyBy('name');

    expect($t['CLINICA SAUDE']['heading'])->toBe('Hospitais')->and($t['PADARIA']['heading'])->toBeNull()->and($t['MERCADO BOM']['heading'])->toBeNull();
});

it('a seção "Parcelamentos Próxima Fatura" não vira compra deste mês', function () {
    $nomes = array_column(fatura()['transactions'], 'name');

    expect(array_count_values($nomes)['GELADEIRA'] ?? 0)->toBe(1);
});

it('o identificador é estável e único (mesma compra repetida ganha um sufixo)', function () {
    $a = array_column(fatura()['transactions'], 'id');
    $b = array_column(fatura()['transactions'], 'id');

    expect($a)->toBe($b)->and(array_unique($a))->toHaveCount(count($a));

    $texto = "Lançamentos nesta fatura\nFatura fechada em 25/11/2026\nVencimento 06/12/2026\n01- A  Cartao N. 1111 Compras diversas CAFE        CIDADE03/11\tBR\tR$ 5,00 CAFE        CIDADE03/11\tBR\tR$ 5,00 ";
    $ids = array_column((new OurocardPdfParser)->parseText($texto)['transactions'], 'id');
    expect(array_unique($ids))->toHaveCount(2);
});

it('virada de ano: fatura de janeiro com compra em dezembro é do ano anterior', function () {
    $texto = "Lançamentos nesta fatura\nFatura fechada em 25/01/2027\nVencimento 06/02/2027\n01- A  Cartao N. 1111 Compras diversas LOJA        CIDADE28/12\tBR\tR$ 5,00 LOJA        CIDADE03/01\tBR\tR$ 7,00 ";
    $t = (new OurocardPdfParser)->parseText($texto)['transactions'];

    expect($t[0]['date'])->toBe('2026-12-28')->and($t[1]['date'])->toBe('2027-01-03');
});

it('compra em dólar não é importada e avisa', function () {
    $texto = "Lançamentos nesta fatura\nFatura fechada em 25/11/2026\n01- A  Cartao N. 1111 Compras diversas OPENAI        SAN FRANCISCO03/11\tUS\tUS$ 20,00 PADARIA        CIDADE04/11\tBR\tR$ 5,00 ";
    $f = (new OurocardPdfParser)->parseText($texto);

    expect(array_column($f['transactions'], 'name'))->toBe(['PADARIA'])->and($f['warnings'][0])->toContain('dólar');
});

it('arquivo que não é fatura do Ourocard dá erro claro', function () {
    expect(fn () => (new OurocardPdfParser)->parseText('um texto qualquer'))->toThrow(InvalidImportFile::class, 'Ourocard');
    expect(fn () => (new OurocardPdfParser)->parse('isto não é um pdf'))->toThrow(InvalidImportFile::class);
});
