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

it('cartões já cadastrados com nome parecido: sugere o certo e não cria repetido', function () {
    [$eliel] = makeCouple();
    $bradescoAna = makeCard($eliel, 'Bradesco Ana');
    $cresol = makeCard($eliel, 'Cresol');

    $tela = Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->assertSet('cardMap.0', $bradescoAna->id)   // "Bradesco" combina com "Bradesco Ana"
        ->assertSet('cardMap.1', $cresol->id)        // nome igual
        ->assertSeeHtml('data-testid="card-select-0"');

    $tela->call('confirm');

    expect(Card::count())->toBe(2)  // nenhum cartão novo
        ->and(Entry::where('card_id', $bradescoAna->id)->count())->toBeGreaterThan(0);
});

it('dá para trocar o cartão ou escolher "Criar novo" na prévia', function () {
    [$eliel] = makeCouple();
    $outro = makeCard($eliel, 'Outro');
    $outro->update(['due_day' => 9]);

    $tela = Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->assertSet('cardMap.0', null)->set('cardMap.0', $outro->id)->assertSet('dueDays.0', 9)->set('cardMap.1', '');

    $tela->call('confirm');

    expect(Entry::where('card_id', $outro->id)->count())->toBeGreaterThan(0)
        ->and(Card::where('name', 'Cresol')->exists())->toBeTrue()   // o 2º virou cartão novo, pelo nome da planilha
        ->and(Card::where('name', 'Bradesco')->exists())->toBeFalse(); // o 1º foi para o "Outro"
});

it('não aceita mapear para um cartão de outro casal', function () {
    [$eliel] = makeCouple();
    [$estranho] = makeCouple('Outro', 'Casal');
    $alheio = makeCard($estranho, 'Alheio');

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')->set('cardMap.0', $alheio->id)->call('confirm');

    expect(Entry::where('card_id', $alheio->id)->count())->toBe(0)->and(Card::withoutGlobalScopes()->where('name', 'Bradesco')->exists())->toBeTrue();
});

it('prévia: cada item tem caixinha, nome, valor e categoria; categoria é sugerida pelo nome', function () {
    [$eliel] = makeCouple();

    $tela = Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->assertSeeHtml('data-testid="items"')->assertSeeHtml('data-testid="item-include"');

    expect(count($tela->get('edits')))->toBeGreaterThan(3)
        ->and($tela->get('edits.c0l0.include'))->toBeTrue();
});

it('desmarcar itens tira da importação e do total da prévia', function () {
    [$eliel] = makeCouple();

    $tela = Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->assertSeeHtml('data-testid="preview-card">R$ 600,00<')
        ->set('edits.c0l0.include', false)->assertSeeHtml('data-testid="preview-card">R$ 300,00<');

    $tela->call('confirm');

    expect(Entry::count())->toBe(1); // das 2 compras fixas do Bradesco sobrou 1
});

it('editar nome, valor e categoria vale na gravação', function () {
    [$eliel] = makeCouple();
    $lazer = categoryOf($eliel->couple_id, 'Lazer')->id;

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->set('edits.c0l0.name', 'Netflix')->set('edits.c0l0.amount', '45,90')->set('edits.c0l0.category_id', $lazer)->call('confirm');

    $e = Entry::where('name', 'Netflix')->first();
    expect($e)->not->toBeNull()->and($e->amount_cents)->toBe(4590)->and($e->category_id)->toBe($lazer);
});

it('valor inválido num item mostra erro claro e não grava nada', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->set('edits.c0l0.amount', 'abc')->call('confirm')->assertSee('Valor inválido em');

    expect(Entry::count())->toBe(0);
});

it('desmarcar tudo não deixa importar', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->call('selectAll', false)->call('confirm')->assertSee('Marque ao menos um item');

    expect(Entry::count())->toBe(0);
});

it('falha inesperada na gravação mostra o motivo na tela em vez de ficar muda', function () {
    [$eliel] = makeCouple();
    $tela = Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview');

    // simula um erro de banco ao gravar
    Entry::saving(fn () => throw new RuntimeException('coluna muito longa'));

    $tela->call('confirm')->assertSee('Não consegui gravar a importação')->assertSee('RuntimeException')->assertSee('coluna muito longa');
});

it('mês com dados: avisa quantos lançamentos tem e oferece importar mesmo assim', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['month' => '2026-09-01', 'name' => 'Já existia']);

    $tela = Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->assertSee('já tem 1 lançamentos')->assertSeeHtml('data-testid="merge-option"')->assertSet('payload', null);

    $tela->set('merge', true)->call('preview')->assertSeeHtml('data-testid="preview"')->call('confirm')->assertSee('Importado:');
    expect(Entry::where('name', 'Já existia')->count())->toBe(1)->and(Entry::count())->toBeGreaterThan(1);
});

it('importar por cima pula o que já existe igual e não duplica', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')->call('confirm');
    $primeira = Entry::count();

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')
        ->set('merge', true)->call('preview')->call('confirm')->assertSee('já existiam iguais e foram pulados');

    expect(Entry::count())->toBe($primeira);
});

it('trocar o mês limpa o aviso e a opção de importar por cima', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['month' => '2026-09-01']);

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', arquivo())->call('preview')->set('merge', true)
        ->set('month', '2026-10')->assertSet('merge', false)->assertSet('existingCount', null)->call('preview')->assertSeeHtml('data-testid="preview"');
});
