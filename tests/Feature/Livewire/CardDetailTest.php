<?php

use App\Livewire\Cards\CardDetail;
use App\Livewire\Cards\CardsPage;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Models\Entry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

it('a lista de cartões é compacta: total, nº de compras e link para a tela do cartão (sem as compras)', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    makeEntry($eliel, ['card_id' => $card->id, 'name' => 'SHEIN', 'amount_cents' => 12000]);
    makeEntry($eliel, ['card_id' => $card->id, 'name' => 'Uber', 'amount_cents' => 2400]);

    Livewire::actingAs($eliel)->test(CardsPage::class)->set('month', '2026-09')
        ->assertSeeHtml('data-testid="card-count">2 compras')->assertSee('R$ 144,00')->assertSeeHtml(route('cards.show', ['card' => $card->id]))
        ->assertDontSee('SHEIN')->assertDontSee('Uber');
});

it('a tela do cartão mostra só as compras dele, com categoria, parcela e valor', function () {
    [$eliel] = makeCouple();
    $nubank = makeCard($eliel, 'Nubank');
    $outro = makeCard($eliel, 'Pagbank');
    makeEntry($eliel, ['card_id' => $nubank->id, 'name' => 'SHEIN', 'amount_cents' => 12000, 'installment_label' => '2/5', 'category_id' => categoryOf($eliel->couple_id, 'Lazer')->id]);
    makeEntry($eliel, ['card_id' => $outro->id, 'name' => 'Compra do outro cartão', 'amount_cents' => 5000]);

    $tela = Livewire::actingAs($eliel)->test(CardDetail::class, ['card' => $nubank])->set('month', '2026-09')
        ->assertSee('Nubank')->assertSeeHtml('data-testid="invoice-total">R$ 120,00<')->assertSee('SHEIN')->assertSee('Lazer')->assertSee('parcela 2/5')
        ->assertDontSee('Compra do outro cartão')->assertSeeHtml('data-testid="line-edit"');

    expect(substr_count($tela->html(), 'data-testid="card-line"'))->toBe(1);
});

it('cada compra tem editar (leva à edição) e excluir; excluir tira a compra e a fatura', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    $compra = makeEntry($eliel, ['card_id' => $card->id, 'name' => 'Uber', 'amount_cents' => 2400]);

    $tela = Livewire::actingAs($eliel)->test(CardDetail::class, ['card' => $card])->set('month', '2026-09')
        ->assertSeeHtml(route('entry.show', $compra->id))->assertSeeHtml('data-testid="line-delete"');

    $tela->call('deleteLine', $compra->id)->assertDontSee('Uber')->assertSeeHtml('data-testid="invoice-total">R$ 0,00<')->assertSeeHtml('data-testid="no-lines"');
    expect(Entry::count())->toBe(0);
});

it('compra parcelada ou todo mês não é excluída direto: o ícone leva à edição, onde se escolhe o escopo', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $primeira = fixo($eliel, ['frequency' => 'monthly', 'charge_day' => 5], 30000, '2026-09-01');
    $primeira->update(['card_id' => makeCard($eliel, 'Nubank')->id]);
    $card = Card::first();

    Livewire::actingAs($eliel)->test(CardDetail::class, ['card' => $card])->set('month', '2026-09')
        ->assertSeeHtml('data-testid="line-delete-scope"')->assertDontSeeHtml('data-testid="line-delete"')->assertSee('todo mês');
});

it('compra do parceiro não mostra editar nem excluir; na visão "Par" nada é editável', function () {
    [$eliel, $ana] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    makeEntry($ana, ['card_id' => $card->id, 'name' => 'Da Ana', 'amount_cents' => 1000]);
    makeEntry($eliel, ['card_id' => $card->id, 'name' => 'Do Eliel', 'amount_cents' => 2000]);

    $tela = Livewire::actingAs($eliel)->test(CardDetail::class, ['card' => $card])->set('month', '2026-09')->assertSee('Da Ana')->assertSee('Do Eliel');
    expect(substr_count($tela->html(), 'data-testid="line-edit"'))->toBe(1);

    $tela->call('setView', 'par')->assertDontSeeHtml('data-testid="line-edit"')->assertDontSeeHtml('data-testid="line-delete"');
});

it('não dá para excluir compra de outro cartão nem a do parceiro por esta tela', function () {
    [$eliel, $ana] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    $outro = makeCard($eliel, 'Pagbank');
    $doOutro = makeEntry($eliel, ['card_id' => $outro->id, 'name' => 'X']);
    $daAna = makeEntry($ana, ['card_id' => $card->id, 'name' => 'Y']);

    $tela = Livewire::actingAs($eliel)->test(CardDetail::class, ['card' => $card])->set('month', '2026-09');
    expect(fn () => $tela->call('deleteLine', $doOutro->id))->toThrow(ModelNotFoundException::class); // vira 404 no navegador
    $tela->call('deleteLine', $daAna->id)->assertForbidden();
    expect(Entry::count())->toBe(2);
});

it('marcar a fatura como paga e voltar; editar cartão abre o cadastro; excluir cartão volta para a lista', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    makeEntry($eliel, ['card_id' => $card->id, 'amount_cents' => 5000]);

    $tela = Livewire::actingAs($eliel)->test(CardDetail::class, ['card' => $card])->set('month', '2026-09')
        ->call('toggleInvoice')->assertSee('fatura paga ✓')->assertSee('Fatura paga ✓');
    expect(CardInvoice::first()->paid)->toBeTrue();
    $tela->call('toggleInvoice');
    expect(CardInvoice::first()->paid)->toBeFalse();

    Livewire::actingAs($eliel)->withQueryParams(['editar' => $card->id])->test(CardsPage::class)->assertSet('editingId', $card->id)->assertSet('name', 'Nubank');

    $tela->call('removeCard')->assertRedirect(route('cards'));
    expect(Card::count())->toBe(0);
});

it('a tela do cartão é do casal: outro casal não acessa (404)', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    [$outro] = makeCouple('Outro', 'Casal');

    $this->actingAs($outro)->get(route('cards.show', $card->id))->assertNotFound();
    $this->actingAs($eliel)->get(route('cards.show', $card->id))->assertOk()->assertSee('Nubank');
});
