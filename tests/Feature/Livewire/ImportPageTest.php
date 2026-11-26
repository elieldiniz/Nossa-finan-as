<?php

use App\Livewire\Import\ImportPage;
use App\Models\Entry;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
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

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')
        ->set('file', arquivo(OrcMesFixture::setembro(['sheet' => 'Outra'])))->call('preview')
        ->assertSeeHtml('data-testid="form-error"')->assertSee('Orç - Mês');
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
