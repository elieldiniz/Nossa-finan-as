<?php

use App\Import\InvalidImportFile;
use App\Import\OrcMesImport;
use App\Support\Money;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Fixtures\OrcMesFixture;

it('lê o caso de Setembro: Bradesco 600, Madrinha +115, Vale 2.250 e fechamento −485', function () {
    $payload = (new OrcMesImport)->parse(OrcMesFixture::setembro());

    $bradesco = $payload->cards[0];
    expect($bradesco['name'])->toBe('Bradesco')
        ->and($payload->cardTotal($bradesco))->toBe(60000)
        ->and($payload->cards[1]['name'])->toBe('Cresol')->and($payload->cardTotal($payload->cards[1]))->toBe(0)
        ->and($payload->personNet($payload->people[0]))->toBe(11500)
        ->and($payload->people[0]['name'])->toBe('Madrinha')
        ->and($payload->vale['amount'])->toBe(225000)
        ->and($payload->detectedMonth)->toBe(9)
        ->and($payload->closing()->closing)->toBe(-48500)
        ->and(Money::signed($payload->closing()->closing))->toBe("\u{2212}R\$ 485,00");
});

it('ignora linhas que são só fórmulas de outros blocos (cartão e saldo de pessoa)', function () {
    $payload = (new OrcMesImport)->parse(OrcMesFixture::setembro());

    expect($payload->entries)->toBe([]); // nada de "Cartão Bradesco" nem "saldo da Madrinha" duplicados
});

it('lê entradas e saídas avulsas com o estado Pago', function () {
    $payload = (new OrcMesImport)->parse(OrcMesFixture::setembro([
        'entradas' => [['Salário', 5000, true]],
        'saidas' => [['Aluguel', 1200.5, false], ['Internet', 99.9, true]],
    ]));

    expect($payload->entries)->toBe([
        ['type' => 'entrada', 'name' => 'Salário', 'amount' => 500000, 'paid' => true],
        ['type' => 'saida', 'name' => 'Aluguel', 'amount' => 120050, 'paid' => false],
        ['type' => 'saida', 'name' => 'Internet', 'amount' => 9990, 'paid' => true],
    ]);
});

it('distingue fixo de parcelado pela coluna Parcelamento/Data', function () {
    $payload = (new OrcMesImport)->parse(OrcMesFixture::setembro(['bradesco' => [['2/10', 'Geladeira', 150], ['sexta', 'Misterioso', 20]]]));

    $linhas = $payload->cards[0]['lines'];
    expect($linhas[0]['kind'])->toBe('fixo')
        ->and($linhas[2])->toMatchArray(['kind' => 'parcelado', 'name' => 'Geladeira', 'amount' => 15000, 'installment' => [2, 10]])
        ->and($linhas[3]['kind'])->toBe('avulso')
        ->and($payload->warnings)->toHaveCount(1);
});

it('lê os gastos do vale', function () {
    $payload = (new OrcMesImport)->parse(OrcMesFixture::setembro(['vale_gastos' => [['Lanche', 250]]]));

    expect($payload->vale['spends'])->toBe([['name' => 'Lanche', 'amount' => 25000]]);
});

it('rejeita aba errada, arquivo que não é planilha e planilha sem os blocos', function () {
    $parser = new OrcMesImport;

    $outra = new Spreadsheet;
    $outra->getActiveSheet()->setTitle('Outra aba')->setCellValue('A1', 'nada a ver');
    $caminhoOutra = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    (new Xlsx($outra))->save($caminhoOutra);
    expect(fn () => $parser->parse($caminhoOutra))->toThrow(InvalidImportFile::class);

    $texto = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    file_put_contents($texto, 'isto não é uma planilha');
    expect(fn () => $parser->parse($texto))->toThrow(InvalidImportFile::class);

    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('Orç - Mês')->setCellValue('A1', 'vazia');
    $vazia = tempnam(sys_get_temp_dir(), 'v').'.xlsx';
    (new Xlsx($book))->save($vazia);
    expect(fn () => $parser->parse($vazia))->toThrow(InvalidImportFile::class);
});

it('processa uma planilha com 500 linhas em até 10 segundos', function () {
    $book = new Spreadsheet;
    $s = $book->getActiveSheet()->setTitle('Orç - Mês');
    $s->setCellValue('B4', 'Entradas')->setCellValue('B5', 'Pago');
    $s->setCellValue('B6', 'Saídas'); // placeholder: reposicionado abaixo
    $s->setCellValue('B6', null);
    $saidaRow = 6 + 250;
    for ($r = 6; $r < $saidaRow; $r++) {
        $s->setCellValue("C{$r}", "Entrada {$r}")->setCellValue("D{$r}", 10 + $r)->setCellValue("B{$r}", true);
    }
    $s->setCellValue("B{$saidaRow}", 'Saídas')->setCellValue('B'.($saidaRow + 1), 'Pago');
    for ($r = $saidaRow + 2; $r < $saidaRow + 252; $r++) {
        $s->setCellValue("C{$r}", "Saída {$r}")->setCellValue("D{$r}", 5 + $r);
    }
    $s->setCellValue('B'.($saidaRow + 252), 'FECHAMENTO DO MÊS');
    $path = tempnam(sys_get_temp_dir(), 'g').'.xlsx';
    (new Xlsx($book))->save($path);

    $inicio = hrtime(true);
    $payload = (new OrcMesImport)->parse($path);

    expect(count($payload->entries))->toBe(500)->and((hrtime(true) - $inicio) / 1e9)->toBeLessThan(10.0);
});

it('planilha com várias abas "Orç - Mês <mês>": lista as abas e lê cada uma', function () {
    $path = OrcMesFixture::variosMeses();
    $import = new OrcMesImport;

    expect($import->sheetNames($path))->toBe(['Orç - Mês Outubro', 'Orç - Mês Novembro']);

    $outubro = $import->parse($path, 'Orç - Mês Outubro');
    $novembro = $import->parse($path, 'Orç - Mês Novembro');
    $primeira = $import->parse($path); // sem informar: a primeira aba

    expect($outubro->detectedMonth)->toBe(9)->and($novembro->detectedMonth)->toBe(10)->and($primeira->detectedMonth)->toBe(9)
        ->and(count($outubro->entries))->toBe(2); // Salário e Aluguel (o "Cartão Sicoob" é fórmula e fica de fora)
});

it('cartões sem linha de cabeçalho, datas, Recorrente, Compras a vista e Total', function () {
    $p = (new OrcMesImport)->parse(OrcMesFixture::variosMeses(), 'Orç - Mês Outubro');

    expect(array_column($p->cards, 'name'))->toBe(['Sicoob', 'Nubank'])->and($p->warnings)->toBe([]);

    [$sicoob, $nubank] = $p->cards;
    // as linhas "Total" não viram compra; o total do cartão bate com o da planilha
    expect(array_sum(array_column($sicoob['lines'], 'amount')))->toBe(16500)->and(array_sum(array_column($nubank['lines'], 'amount')))->toBe(6000);

    expect($sicoob['lines'][0])->toMatchArray(['name' => 'Armário', 'kind' => 'fixo', 'ends_on' => '2027-03-08'])
        ->and($sicoob['lines'][1])->toMatchArray(['name' => 'Google Fotos', 'kind' => 'fixo', 'ends_on' => null])
        ->and($nubank['lines'][0])->toMatchArray(['name' => 'Spotify', 'kind' => 'fixo'])
        ->and($nubank['lines'][1])->toMatchArray(['name' => 'Padaria', 'kind' => 'avulso'])
        ->and($nubank['lines'][2])->toMatchArray(['name' => 'Cinema', 'kind' => 'avulso']);
});

it('arquivo sem aba "Orç - Mês" dá um erro claro', function () {
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('Outra coisa');
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    (new Xlsx($book))->save($path);

    expect(fn () => (new OrcMesImport)->parse($path))->toThrow(InvalidImportFile::class, 'Não reconheci a planilha');
});

it('aba com outro nome ("Planilha1") é reconhecida pela estrutura', function () {
    $path = OrcMesFixture::setembro(['sheet' => 'Planilha1']);
    $import = new OrcMesImport;

    expect($import->sheetNames($path))->toBe(['Planilha1']);

    $p = $import->parse($path);
    expect($p->detectedMonth)->toBe(9)->and(count($p->cards))->toBe(2)->and($p->vale['amount'])->toBe(225000);
});

it('o erro lista as abas encontradas para a pessoa saber o que ajustar', function () {
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('Resumo');
    $book->createSheet()->setTitle('Gráficos');
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    (new Xlsx($book))->save($path);

    expect(fn () => (new OrcMesImport)->parse($path))->toThrow(InvalidImportFile::class, 'Abas encontradas no arquivo: "Resumo", "Gráficos"');
});

it('lê o vencimento escrito no nome do cartão: "Sicoob - vence dia 3"', function () {
    $path = OrcMesFixture::variosMeses();
    $book = IOFactory::load($path);
    foreach ($book->getAllSheets() as $sheet) {
        $sheet->setCellValue('F4', 'Sicoob - vence dia 3')->setCellValue('F15', 'Nubank (vence dia 4)');
    }
    $novo = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    (new Xlsx($book))->save($novo);

    $p = (new OrcMesImport)->parse($novo, 'Orç - Mês Outubro');

    expect(array_column($p->cards, 'name'))->toBe(['Sicoob', 'Nubank'])->and(array_column($p->cards, 'due_day'))->toBe([3, 4])
        ->and(array_sum(array_column($p->cards[0]['lines'], 'amount')))->toBe(16500); // as compras continuam sendo lidas
});

it('cartão sem vencimento no nome fica sem dia (null)', function () {
    $p = (new OrcMesImport)->parse(OrcMesFixture::variosMeses(), 'Orç - Mês Outubro');

    expect(array_column($p->cards, 'due_day'))->toBe([null, null]);
});

it('lê texto com formatação parcial (rich text) como texto comum', function () {
    $book = IOFactory::load(OrcMesFixture::setembro());
    $sheet = $book->getActiveSheet();
    foreach (['B4' => 'Entradas', 'B18' => 'Saídas', 'B32' => 'FECHAMENTO DO MÊS', 'F4' => 'Bradesco - vence dia 12'] as $cell => $texto) {
        $rich = new RichText;
        $rich->createText($texto);
        $sheet->getCell($cell)->setValue($rich);
    }
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    (new Xlsx($book))->save($path);

    $p = (new OrcMesImport)->parse($path);

    expect($p->cards[0]['name'])->toBe('Bradesco')->and($p->cards[0]['due_day'])->toBe(12)->and($p->detectedMonth)->toBe(9);
});
