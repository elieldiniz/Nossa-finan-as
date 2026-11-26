<?php

use App\Livewire\Cards\CardsPage;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Services\EntryService;
use Livewire\Livewire;

it('cria 10 cartões com nomes distintos, sem nenhum pré-cadastrado', function () {
    [$eliel] = makeCouple();
    expect(Card::withoutGlobalScopes()->count())->toBe(0);

    $tela = Livewire::actingAs($eliel)->test(CardsPage::class);
    foreach (range(1, 10) as $i) {
        $tela->set('name', "Cartão {$i}")->call('addCard')->assertHasNoErrors();
    }

    expect(Card::count())->toBe(10);
});

it('2 fixos de 300 geram uma fatura de 600,00', function () {
    [$eliel] = cenarioPlanilha();

    Livewire::actingAs($eliel)->test(CardsPage::class)->set('month', '2026-09')->assertSee('R$ 600,00');
});

it('fatura futura mostra o previsto', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $card = makeCard($eliel);
    app(EntryService::class)->create($eliel, [
        'kind' => 'saida', 'name' => 'Fixo', 'amount_cents' => 30000, 'month' => '2026-09-01', 'card_id' => $card->id,
        'category_id' => categoryOf($eliel->couple_id, 'Moradia')->id, 'repeat' => ['frequency' => 'monthly'],
    ]);

    Livewire::test(CardsPage::class)->set('month', '2030-01')->assertSee('R$ 300,00')->assertSee('Fatura prevista');
});

it('marcar fatura paga persiste', function () {
    [$eliel] = cenarioPlanilha();
    $card = Card::first();

    $tela = Livewire::actingAs($eliel)->test(CardsPage::class)->set('month', '2026-09')->call('toggleInvoice', $card->id);

    expect(CardInvoice::first()->paid)->toBeTrue();
    $tela->assertSee('fatura paga');
});

it('o par também edita cartões (cadastro compartilhado)', function () {
    [, $ana] = makeCouple();
    $card = makeCard($ana);

    Livewire::actingAs($ana)->test(CardsPage::class)->call('edit', $card->id)->set('name', 'Novo nome')->call('addCard');

    expect($card->fresh()->name)->toBe('Novo nome');
});
