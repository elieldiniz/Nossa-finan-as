<?php

use App\Livewire\Cards\CardDetail;
use App\Livewire\Import\OfxPage;
use App\Livewire\Statement\StatementPage;
use App\Models\Card;
use App\Models\Entry;
use App\Models\Recurrence;
use App\Services\DueBills;
use App\Services\OpenMonthService;
use App\Support\ViewContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function arquivoOfx(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('Nubank_2026-12-07.ofx', file_get_contents(__DIR__.'/../../Fixtures/ofx_nubank.ofx'));
}

it('escolher o arquivo mostra as compras, sugere cartão, mês da fatura e categoria; pagamentos/créditos ficam de fora', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');

    Livewire::actingAs($eliel)->test(OfxPage::class)->set('file', arquivoOfx())
        ->assertSet('month', '2026-11')->assertSet('cardId', $card->id)
        ->assertSeeHtml('data-testid="ofx-preview"')->assertSee('Restaurante Fogao A Le')->assertSee('parcela 1/2')
        ->assertSeeHtml('data-testid="ofx-count">5 compras')->assertSeeHtml('data-testid="ofx-total">R$ 413,70<') // 45,16+45+190+23,90+109,64
        ->assertSee('pagamento/crédito: não entra');
});

it('sem cartão parecido, sugere criar "Nubank" e grava tudo, com parceladas continuando nos próximos meses', function () {
    [$eliel] = makeCouple();
    $tela = Livewire::actingAs($eliel)->test(OfxPage::class)->set('file', arquivoOfx())->assertSet('cardId', null)->assertSet('newCardName', 'Nubank')
        ->call('confirm')->assertHasNoErrors()->assertSee('Importado: 5 compras em Nubank')->assertSee('2 parceladas');

    $card = Card::where('name', 'Nubank')->first();
    expect($card)->not->toBeNull()
        ->and(Entry::where('card_id', $card->id)->where('month', '2026-11-01')->count())->toBe(5)
        ->and(Entry::where('card_id', $card->id)->sum('amount_cents'))->toBe(41370)
        ->and(Entry::where('name', 'Jeeda Supermercados')->first()->category->name)->toBe('Mercado')
        ->and(Entry::where('name', 'Dl*Spotify P4949')->first()->category->name)->toBe('Assinatura');

    $havan = Entry::where('name', 'Havan Ji Parana')->first();
    expect($havan->installment_label)->toBe('1/2')->and(Recurrence::where('name', 'Havan Ji Parana')->first()->installments_total)->toBe(2);

    // a parcela 2/2 aparece em dezembro; a 4/4 não tem próxima
    app(OpenMonthService::class)->openRange($eliel->couple_id, CarbonImmutable::parse('2026-11-01'), 3);
    expect(Entry::where('name', 'Havan Ji Parana')->pluck('installment_label')->sort()->values()->all())->toBe(['1/2', '2/2'])
        ->and(Entry::where('name', 'Jim.Com* Gp Motos')->count())->toBe(1);
});

it('importar o mesmo arquivo de novo não duplica nada', function () {
    [$eliel] = makeCouple();
    makeCard($eliel, 'Nubank');

    Livewire::actingAs($eliel)->test(OfxPage::class)->set('file', arquivoOfx())->call('confirm')->assertSee('Importado: 5 compras');
    expect(Entry::count())->toBe(5);

    $tela = Livewire::actingAs($eliel)->test(OfxPage::class)->set('file', arquivoOfx())
        ->assertSee('já importada')->assertSeeHtml('data-testid="ofx-count">0 compras')->call('confirm')->assertSee('Marque ao menos uma compra');
    expect(Entry::count())->toBe(5);
});

it('desmarcar compras e trocar a categoria vale na gravação', function () {
    [$eliel] = makeCouple();
    makeCard($eliel, 'Nubank');
    $lazer = categoryOf($eliel->couple_id, 'Lazer')->id;

    Livewire::actingAs($eliel)->test(OfxPage::class)->set('file', arquivoOfx())
        ->set('lines.0.include', false)->set('lines.4.category_id', $lazer)->assertSeeHtml('data-testid="ofx-count">4 compras')->call('confirm');

    expect(Entry::where('name', 'Restaurante Fogao A Le')->exists())->toBeFalse()
        ->and(Entry::where('name', 'Jeeda Supermercados')->first()->category_id)->toBe($lazer);
});

it('arquivo que não é OFX mostra erro e não grava', function () {
    [$eliel] = makeCouple();
    $ruim = UploadedFile::fake()->createWithContent('x.ofx', 'isso não é um extrato');

    Livewire::actingAs($eliel)->test(OfxPage::class)->set('file', $ruim)->assertSee('não parece um extrato OFX');
    expect(Entry::count())->toBe(0);
});

it('a tela de importar fatura abre e é alcançável pelo Mais e pela planilha', function () {
    [$eliel] = makeCouple();

    $this->actingAs($eliel)->get(route('import.ofx'))->assertOk()->assertSee('Importar fatura do cartão');
    $this->actingAs($eliel)->get(route('import'))->assertSee(route('import.ofx'), false);
});

it('"Vence dia" do cartão: vem preenchido do cartão, grava no cartão e a fatura entra em Contas a vencer', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    expect($card->due_day)->toBeNull();

    $tela = Livewire::actingAs($eliel)->test(OfxPage::class)->set('file', arquivoOfx())->assertSeeHtml('data-testid="due-day"')->assertSet('dueDay', null)
        ->set('dueDay', 7)->call('confirm')->assertHasNoErrors();

    expect($card->fresh()->due_day)->toBe(7);

    $contas = app(DueBills::class)->forMonth(CarbonImmutable::parse('2026-12-01'), ViewContext::for($eliel, 'nos'), CarbonImmutable::parse('2026-12-05'));
    expect(array_column($contas, 'name'))->toBe(['Fatura Nubank (novembro)'])->and($contas[0]['due'])->toBe('2026-12-07')->and($contas[0]['amount'])->toBe(41370);

    // numa próxima importação o dia já vem preenchido
    Livewire::actingAs($eliel)->test(OfxPage::class)->set('file', arquivoOfx())->assertSet('dueDay', 7);
});

it('dia de vencimento inválido mostra erro e não grava', function () {
    [$eliel] = makeCouple();
    makeCard($eliel, 'Nubank');

    Livewire::actingAs($eliel)->test(OfxPage::class)->set('file', arquivoOfx())->set('dueDay', 40)->call('confirm')->assertSee('vai de 1 a 31');
    expect(Entry::count())->toBe(0);
});

it('a data de cada compra é guardada e aparece na tela do cartão e no Extrato', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');

    Livewire::actingAs($eliel)->test(OfxPage::class)->set('file', arquivoOfx())->call('confirm');

    expect(Entry::where('name', 'Restaurante Fogao A Le')->first()->purchased_on->toDateString())->toBe('2026-11-22');

    Livewire::actingAs($eliel)->test(CardDetail::class, ['card' => $card])->set('month', '2026-11')->assertSeeHtml('data-testid="line-date">22/11<');
    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-11')->call('toggleCard', $card->id)->assertSee('22/11');
});

it('com "Nubank" e "Nubank Ana" cadastrados, sugere o de nome igual e dá para trocar', function () {
    [$eliel] = makeCouple();
    makeCard($eliel, 'Nubank Ana');
    $nubank = makeCard($eliel, 'Nubank');

    Livewire::actingAs($eliel)->test(OfxPage::class)->set('file', arquivoOfx())->assertSet('cardId', $nubank->id);
});
