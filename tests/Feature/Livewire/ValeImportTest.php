<?php

use App\Import\InvalidImportFile;
use App\Import\ValeStatementParser;
use App\Livewire\Vale\ImportPage;
use App\Livewire\Vale\ValePage;
use App\Models\Vale;
use App\Models\ValeSpend;
use App\Services\Vale\ValeOfxExporter;
use App\Services\Vale\ValePdfExporter;
use App\Services\Vale\ValeStatement;
use App\Support\ViewContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function ofxDoVale(): string
{
    return <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:UTF-8
CHARSET:NONE
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<BANKMSGSRSV1><STMTTRNRS><TRNUID>1</TRNUID><STMTRS><CURDEF>BRL</CURDEF>
<BANKACCTFROM><BANKID>0</BANKID><ACCTID>cartao-vale</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20261101000000[-3:BRT]</DTSTART>
<DTEND>20261130000000[-3:BRT]</DTEND>
<STMTTRN><TRNTYPE>CREDIT</TRNTYPE><DTPOSTED>20261101000000[-3:BRT]</DTPOSTED><TRNAMT>2250.00</TRNAMT><FITID>rec-1</FITID><MEMO>Recarga beneficio</MEMO></STMTTRN>
<STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20261105000000[-3:BRT]</DTPOSTED><TRNAMT>-450.00</TRNAMT><FITID>d-1</FITID><MEMO>Supermercado Tai Max</MEMO></STMTTRN>
<STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20261108000000[-3:BRT]</DTPOSTED><TRNAMT>-32.00</TRNAMT><FITID>d-2</FITID><MEMO>Padaria Pao Quente</MEMO></STMTTRN>
<STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20261203000000[-3:BRT]</DTPOSTED><TRNAMT>-60.00</TRNAMT><FITID>d-3</FITID><MEMO>Restaurante do Ze</MEMO></STMTTRN>
</BANKTRANLIST>
<LEDGERBAL><BALAMT>1708.00</BALAMT><DTASOF>20261130</DTASOF></LEDGERBAL>
</STMTRS></STMTTRNRS></BANKMSGSRSV1>
</OFX>
OFX;
}

function arquivoVale(string $conteudo, string $nome = 'extrato.ofx'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nome, $conteudo);
}

it('PDF: lê linhas "DD/MM  descrição  R$ valor" com sinal, créditos e categorias do extrato', function () {
    $texto = "Extrato · Novembro de 2026\nData Descrição Valor\n01/11 Crédito do vale alimentação\tR$ 2.250,00\nMercado\n05/11 Mercadão\tR$ 450,00-\n08/11 Padaria\t-R$ 32,00\nLazer\n12/11/2026 Pizza\t(R$ 60,00)\nSubtotal\tR$ 60,00\n15/11 Farmácia R$ 28,90\n";
    $f = (new ValeStatementParser)->parseText($texto);
    $t = collect($f['transactions'])->keyBy('name');

    expect($f['format'])->toBe('pdf')->and($f['start'])->toBe('2026-11-01')->and($f['end'])->toBe('2026-11-15')
        ->and($t['Crédito do vale alimentação']['amount'])->toBe(225000)                         // crédito (positivo)
        ->and($t['Mercadão'])->toMatchArray(['amount' => -45000, 'date' => '2026-11-05', 'heading' => 'Mercado'])
        ->and($t['Padaria']['amount'])->toBe(-3200)                                               // -R$ 32,00
        ->and($t['Pizza'])->toMatchArray(['amount' => -6000, 'date' => '2026-11-12', 'heading' => 'Lazer'])   // (R$ 60,00) com ano
        ->and($t['Farmácia']['amount'])->toBe(-2890);                                             // sem sinal: gasto
    expect(array_key_exists('Subtotal', $t->all()))->toBeFalse();
});

it('PDF: o ano vem do título do mês e a virada de ano funciona', function () {
    $texto = "Dezembro de 2026\n28/12 Loja A R$ 10,00-\nJaneiro de 2027\n03/01 Loja B R$ 20,00-\n";
    $t = (new ValeStatementParser)->parseText($texto)['transactions'];

    expect($t[0]['date'])->toBe('2026-12-28')->and($t[1]['date'])->toBe('2027-01-03');
});

it('PDF sem lançamentos reconhecíveis dá erro claro', function () {
    expect(fn () => (new ValeStatementParser)->parseText("um texto qualquer\nsem datas"))->toThrow(InvalidImportFile::class, 'Não reconheci lançamentos');
    expect(fn () => (new ValeStatementParser)->parsePdf('isto não é um pdf'))->toThrow(InvalidImportFile::class);
});

it('OFX: mostra os gastos, deixa a recarga de fora e sugere o valor do vale', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale(ofxDoVale()))
        ->assertSeeHtml('data-testid="vale-import-preview"')->assertSee('Supermercado Tai Max')->assertSee('recarga/crédito: não entra')
        ->assertSet('valeAmount', 'R$ 2.250,00')->assertSeeHtml('data-testid="vale-import-count"')->assertSee('3 gastos')->assertSee('em 2 meses')->assertSeeHtml('data-testid="vale-import-total">R$ 542,00<');
});

it('quem ainda não tem vale cria o vale ao importar; cada gasto vai para o mês da sua data, com categoria sugerida', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale(ofxDoVale()))->call('confirm')->assertHasNoErrors()->assertSee('Importado: 3 gastos no vale alimentação (em 2 meses)');

    $vale = Vale::first();
    $gastos = ValeSpend::orderBy('spent_on')->get();
    expect($vale->amount_cents)->toBe(225000)->and($gastos)->toHaveCount(3)
        ->and($gastos->pluck('month')->map->format('Y-m')->all())->toBe(['2026-11', '2026-11', '2026-12'])
        ->and($gastos[0])->toMatchArray(['name' => 'Supermercado Tai Max', 'amount_cents' => 45000])->and($gastos[0]->spent_on->toDateString())->toBe('2026-11-05')
        ->and($gastos[0]->category->name)->toBe('Mercado')->and($gastos[2]->external_id)->not->toBeNull();
});

it('sem vale e sem valor informado pede o valor e não grava', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale(ofxDoVale()))->set('valeAmount', '')->call('confirm')->assertSee('Informe o valor do seu vale');
    expect(ValeSpend::count())->toBe(0)->and(Vale::count())->toBe(0);
});

it('importar o mesmo extrato de novo não duplica', function () {
    [$eliel] = makeCouple();
    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale(ofxDoVale()))->call('confirm');
    expect(ValeSpend::count())->toBe(3);

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale(ofxDoVale()))->assertSee('já importado')->assertSeeHtml('data-testid="vale-import-count">0 gastos')->call('confirm')->assertSee('Marque ao menos um gasto');
    expect(ValeSpend::count())->toBe(3);
});

it('quem já tem vale mantém o valor, a menos que marque para alterar', function () {
    [$eliel] = makeCouple();
    makeVale($eliel, 100000);

    $tela = Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale(ofxDoVale()))->assertSeeHtml('data-testid="change-vale"')->assertDontSeeHtml('data-testid="vale-amount"');
    $tela->call('confirm');
    expect(Vale::first()->amount_cents)->toBe(100000);

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale(str_replace(['d-1', 'Supermercado Tai Max'], ['d-9', 'Feira Livre'], ofxDoVale())))->set('changeVale', true)->assertSet('valeAmount', '')->set('valeAmount', 'R$ 2.250,00')->call('confirm');
    expect(Vale::first()->amount_cents)->toBe(225000);
});

it('desmarcar gastos, trocar a categoria e usar só os marcados', function () {
    [$eliel] = makeCouple();
    $lazer = categoryOf($eliel->couple_id, 'Lazer')->id;

    $tela = Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale(ofxDoVale()));
    $idx = collect($tela->get('lines'))->search(fn ($l) => $l['name'] === 'Restaurante do Ze');
    $outro = collect($tela->get('lines'))->search(fn ($l) => $l['name'] === 'Padaria Pao Quente');

    $tela->set("lines.{$outro}.include", false)->set("lines.{$idx}.category_id", $lazer)->assertSeeHtml('data-testid="vale-import-count">2 gastos')->call('confirm');

    expect(ValeSpend::pluck('name')->sort()->values()->all())->toBe(['Restaurante do Ze', 'Supermercado Tai Max'])->and(ValeSpend::where('name', 'Restaurante do Ze')->first()->category_id)->toBe($lazer);
});

it('ciclo completo: exportar o extrato do vale (OFX e PDF) e importar de volta traz os mesmos gastos', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $vale = makeVale($eliel, 225000);
    foreach ([['Mercadão', 45000, 'Mercado', '2026-11-05'], ['Pizza', 6000, 'Lazer', '2026-11-12']] as [$nome, $cents, $cat, $dia]) {
        $s = new ValeSpend(['vale_id' => $vale->id, 'owner_id' => $eliel->id, 'name' => $nome, 'amount_cents' => $cents, 'month' => '2026-11-01', 'spent_on' => $dia, 'category_id' => categoryOf($eliel->couple_id, $cat)->id]);
        $s->couple_id = $eliel->couple_id;
        $s->save();
    }
    $statement = app(ValeStatement::class)->build(ViewContext::for($eliel, 'eu'), CarbonImmutable::parse('2026-11-01'));
    $ofx = app(ValeOfxExporter::class)->render($statement);
    $pdf = app(ValePdfExporter::class)->render($statement);
    ValeSpend::query()->delete();

    // OFX de volta
    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale($ofx))->assertSeeHtml('data-testid="vale-import-count">2 gastos')->call('confirm');
    expect(ValeSpend::orderBy('spent_on')->get()->map(fn ($s) => [$s->name, $s->amount_cents, $s->spent_on->toDateString()])->all())
        ->toBe([['Mercadão', 45000, '2026-11-05'], ['Pizza', 6000, '2026-11-12']]);

    // PDF de volta (depois de apagar), com as categorias do extrato
    ValeSpend::query()->delete();
    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale($pdf, 'extrato.pdf'))->assertSeeHtml('data-testid="vale-import-count">2 gastos')->call('confirm');
    $voltou = ValeSpend::with('category')->orderBy('spent_on')->get();
    expect($voltou->pluck('name')->all())->toBe(['Mercadão', 'Pizza'])->and($voltou->pluck('amount_cents')->all())->toBe([45000, 6000])
        ->and($voltou->pluck('category.name')->all())->toBe(['Mercado', 'Lazer'])->and($voltou->pluck('spent_on')->map->toDateString()->all())->toBe(['2026-11-05', '2026-11-12']);
});

it('arquivo que não é extrato mostra erro e a tela tem o botão de importar na tela do vale', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale('isso não é um extrato'))->assertSee('não parece um extrato OFX');
    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', UploadedFile::fake()->createWithContent('x.txt', 'nada'))->assertSee('.ofx ou .pdf');

    $this->actingAs($eliel)->get(route('vale'))->assertSee('Importar extrato (.ofx ou .pdf)')->assertSee(route('vale.import'), false);
    $this->actingAs($eliel)->get(route('vale.import'))->assertOk()->assertSee('Importar extrato do vale alimentação');
});

it('na visão "Par" a importação não aparece e a tela de importar só grava no vale de quem está logado', function () {
    [$eliel, $ana] = makeCouple();
    makeVale($ana, 150000);

    Livewire::actingAs($eliel)->test(ValePage::class)->call('setView', 'par')->assertDontSeeHtml('data-testid="import-link"');

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', arquivoVale(ofxDoVale()))->call('confirm');
    expect(ValeSpend::where('owner_id', $ana->id)->count())->toBe(0)->and(ValeSpend::where('owner_id', $eliel->id)->count())->toBe(3);
});
