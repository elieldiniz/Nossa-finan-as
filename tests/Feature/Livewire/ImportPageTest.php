<?php

use App\Livewire\Import\ImportPage;
use App\Models\Card;
use App\Models\Entry;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Fixtures\OrcMesFixture;

function arquivo(?string $path = null)
{
    return UploadedFile::fake()->createWithContent('planilha.xlsx', file_get_contents($path ?? OrcMesFixture::setembro()));
}

it('a prévia mostra Bradesco 600,00, Madrinha +115,00, Vale 2.250,00 e fechamento −485,00 sem gravar nada', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-testid="preview"')
        ->assertSeeHtml('data-testid="preview-closing">'."\u{2212}R\$ 485,00")
        ->assertSee('R$ 600,00')->assertSee('+R$ 115,00')->assertSee('R$ 2.250,00');

    expect(Entry::withoutGlobalScopes()->count())->toBe(0);
});

it('só grava depois de confirmar', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->call('confirm')->assertSeeHtml('data-testid="done"');

    expect(Entry::withoutGlobalScopes()->count())->toBe(2);
});

it('arquivo que não é planilha é rejeitado com mensagem e nada é gravado', function () {
    [$eliel] = makeCouple();
    $texto = UploadedFile::fake()->createWithContent('lista.txt', 'oi');

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', $texto)->call('preview')
        ->assertHasErrors('file')->assertSeeHtml('data-testid="file-error"');

    expect(Entry::withoutGlobalScopes()->count())->toBe(0);
});

it('planilha sem a aba certa é rejeitada com mensagem', function () {
    [$eliel] = makeCouple();

    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('Resumo')->setCellValue('A1', 'nada a ver');
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    (new Xlsx($book))->save($path);

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo($path))->call('preview')
        ->assertSeeHtml('data-testid="form-error"')->assertSee('Orç - Mês')->assertSee('Abas encontradas no arquivo: "Resumo"');
});

it('mês que já tem dados é recusado na prévia', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['month' => '2026-09-01']);

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->assertSeeHtml('data-testid="form-error"')->assertDontSeeHtml('data-testid="preview"');
});

it('dá para trocar o dono de um item na prévia', function () {
    [$eliel, $ana] = makeCouple();
    $tela = Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')
        ->set('file', arquivo(OrcMesFixture::setembro(['entradas' => [['Salário', 100, false]]])))->call('preview');

    $tela->set('owners.e0', $ana->id)->call('confirm');

    expect(Entry::withoutGlobalScopes()->where('name', 'Salário')->first()->owner_id)->toBe($ana->id);
});

it('planilha com várias abas: mostra a escolha da aba e sugere o mês pelo título', function () {
    [$eliel] = makeCouple();
    $arquivo = UploadedFile::fake()->createWithContent('planilha.xlsx', file_get_contents(OrcMesFixture::variosMeses()));

    $tela = Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', $arquivo)
        ->assertSet('sheets', ['Orç - Mês Outubro', 'Orç - Mês Novembro'])->assertSet('sheet', 'Orç - Mês Outubro')
        ->assertSeeHtml('data-testid="sheet-select"')->assertSet('month', now()->year.'-09');

    $tela->set('sheet', 'Orç - Mês Novembro')->assertSet('month', now()->year.'-10')
        ->call('preview')->assertHasNoErrors()->assertSee('Spotify')->assertSee('Armário');
});

it('o arquivo com uma aba só não pergunta qual importar', function () {
    [$eliel] = makeCouple();
    $arquivo = UploadedFile::fake()->createWithContent('planilha.xlsx', file_get_contents(OrcMesFixture::setembro()));

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('file', $arquivo)->assertDontSeeHtml('data-testid="sheet-select"')->assertSet('month', now()->year.'-09');
});

it('planilha: "Vence dia" de cada cartão grava no cartão', function () {
    [$eliel] = makeCouple();

    $tela = Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->assertSeeHtml('data-testid="due-days"')->assertSeeHtml('data-testid="due-day-0"')->set('dueDays.0', 10)->call('confirm');

    expect(Card::orderBy('id')->first()->due_day)->toBe(10);
});

it('planilha com a aba chamada "Planilha1" (nome diferente) importa normalmente', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo(OrcMesFixture::setembro(['sheet' => 'Planilha1'])))->call('preview')
        ->assertHasNoErrors()->assertSeeHtml('data-testid="preview"')->assertSee('Bradesco');
});

it('planilha com "Sicoob - vence dia 3": o dia vem preenchido na prévia e grava no cartão', function () {
    [$eliel] = makeCouple();
    $book = IOFactory::load(OrcMesFixture::setembro());
    $book->getActiveSheet()->setCellValue('F4', 'Bradesco - vence dia 12');
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    (new Xlsx($book))->save($path);

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo($path))->call('preview')
        ->assertSet('dueDays.0', 12)->call('confirm');

    $card = Card::where('name', 'Bradesco')->first();
    expect($card)->not->toBeNull()->and($card->due_day)->toBe(12);
});
