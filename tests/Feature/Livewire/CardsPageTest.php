<?php

use App\Livewire\Cards\CardsPage;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Models\Entry;
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

it('Limpeza em massa: aparece embaixo; "Excluir todas as faturas" mostra o resumo por cartão, exclui e desfaz', function () {
    [$eliel] = makeCouple();
    $a = makeCard($eliel, 'Nubank');
    $b = makeCard($eliel, 'Ourocard');
    makeEntry($eliel, ['card_id' => $a->id, 'name' => 'A', 'amount_cents' => 1000]);
    makeEntry($eliel, ['card_id' => $b->id, 'name' => 'B', 'amount_cents' => 2000]);

    $tela = Livewire::actingAs($eliel)->test(CardsPage::class)->set('month', '2026-09')->assertSeeHtml('data-testid="bulk"')
        ->call('openCleanAll')->assertSeeHtml('data-testid="clean-all-dialog"')->assertSeeHtml('data-testid="clean-all-count">2<')->assertSee('Nubank')->assertSee('Ourocard');

    $tela->call('cleanAll')->assertSee('2 compras excluídas de todos os cartões (R$ 30,00)')->assertSeeHtml('data-testid="clean-undo"');
    expect(Entry::count())->toBe(0);

    $tela->call('undoClean')->assertSee('2 compras restauradas');
    expect(Entry::count())->toBe(2);
});

it('"todos os meses" liga o parar parceladas; sem cartões só o "apagar tudo" aparece na limpeza', function () {
    [$eliel] = makeCouple();
    Livewire::actingAs($eliel)->test(CardsPage::class)->assertSeeHtml('data-testid="open-reset"')->assertDontSeeHtml('data-testid="open-clean-all"')->assertDontSeeHtml('data-testid="open-delete-cards"');

    makeCard($eliel, 'Nubank');
    Livewire::actingAs($eliel)->test(CardsPage::class)->call('openCleanAll')->assertSet('stopSeries', false)->set('cleanScope', 'all')->assertSet('stopSeries', true);
});

it('Excluir todos os cartões exige digitar EXCLUIR e pergunta o que fazer com as compras', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    makeEntry($eliel, ['card_id' => $card->id, 'name' => 'A']);

    $tela = Livewire::actingAs($eliel)->test(CardsPage::class)->call('openDeleteCards')->assertSeeHtml('data-testid="delete-cards-dialog"')
        ->assertSee('Manter as compras como lançamentos sem cartão')->assertSee('Apagar as compras também')->assertSee('não dá para desfazer');

    $tela->set('confirmText', 'sim')->call('deleteAllCards')->assertSeeHtml('data-testid="confirm-error"');
    expect(Card::count())->toBe(1);

    $tela->set('deleteMode', 'purge')->set('confirmText', 'excluir')->call('deleteAllCards')->assertSee('1 cartões excluídos e 1 compras apagadas');
    expect(Card::count())->toBe(0)->and(Entry::count())->toBe(0);
});

it('Excluir todos os cartões mantendo as compras deixa os lançamentos sem cartão', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    makeEntry($eliel, ['card_id' => $card->id, 'name' => 'A']);

    Livewire::actingAs($eliel)->test(CardsPage::class)->call('openDeleteCards')->set('confirmText', 'EXCLUIR')->call('deleteAllCards')->assertSee('As compras ficaram como lançamentos sem cartão');

    expect(Card::count())->toBe(0)->and(Entry::count())->toBe(1)->and(Entry::first()->card_id)->toBeNull();
});

it('na visão "Par" a limpeza em massa não aparece', function () {
    [$eliel] = makeCouple();
    makeCard($eliel, 'Nubank');

    Livewire::actingAs($eliel)->test(CardsPage::class)->call('setView', 'par')->assertDontSeeHtml('data-testid="bulk"');
});

it('Apagar tudo e recomeçar: mostra o resumo, exige a frase e a senha e apaga tudo do usuário', function () {
    [$eliel] = makeCouple();
    $eliel->forceFill(['password' => bcrypt('senha-certa')])->save();
    $card = makeCard($eliel, 'Nubank');
    makeEntry($eliel, ['card_id' => $card->id, 'name' => 'A']);
    makeEntry($eliel, ['name' => 'B']);

    $tela = Livewire::actingAs($eliel)->test(CardsPage::class)->call('openReset')->assertSeeHtml('data-testid="reset-dialog"')->assertSeeHtml('data-testid="reset-entries">2<')->assertSee('não dá para desfazer');

    $tela->set('resetPhrase', 'apagar')->set('resetPassword', 'senha-certa')->call('resetAll')->assertSeeHtml('data-testid="reset-phrase-error"');
    expect(Entry::count())->toBe(2);

    $tela->set('resetPhrase', 'apagar tudo')->set('resetPassword', 'errada')->call('resetAll')->assertSeeHtml('data-testid="reset-password-error"');
    expect(Entry::count())->toBe(2)->and(Card::count())->toBe(1);

    $tela->set('resetPassword', 'senha-certa')->call('resetAll')->assertSee('Tudo o que você cadastrou foi apagado')->assertDontSeeHtml('data-testid="reset-dialog"');
    expect(Entry::withTrashed()->count())->toBe(0)->and(Card::count())->toBe(0);
});

it('apagar tudo não aparece na visão "Par" e só apaga o que é do próprio usuário', function () {
    [$eliel, $ana] = makeCouple();
    $ana->forceFill(['password' => bcrypt('x-senha')])->save();
    makeEntry($eliel, ['name' => 'Do Eliel']);
    makeEntry($ana, ['name' => 'Da Ana']);

    Livewire::actingAs($ana)->test(CardsPage::class)->call('setView', 'par')->assertDontSeeHtml('data-testid="open-reset"');

    Livewire::actingAs($ana)->test(CardsPage::class)->call('openReset')->set('resetPhrase', 'APAGAR TUDO')->set('resetPassword', 'x-senha')->call('resetAll');
    expect(Entry::where('name', 'Da Ana')->count())->toBe(0)->and(Entry::where('name', 'Do Eliel')->count())->toBe(1);
});
