<?php

use App\Livewire\Home\HomePage;
use App\Models\CardInvoice;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('o Início mostra "Contas a vencer" com quantos dias faltam', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00'));
    makeEntry($eliel, ['name' => 'Aluguel', 'due_on' => '2026-10-05', 'amount_cents' => 150000]);
    makeEntry($eliel, ['name' => 'Internet', 'due_on' => '2026-10-06']);
    makeEntry($eliel, ['name' => 'Luz', 'due_on' => '2026-10-09']);

    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-10')
        ->assertSeeHtml('data-testid="due-bills"')
        ->assertSee('vence hoje')->assertSee('vence amanhã')->assertSee('vence em 4 dias')->assertSee('R$ 1.500,00');
});

it('sem nada a vencer explica como aparecer ali', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(HomePage::class)->assertSeeHtml('data-testid="no-bills"')->assertSee('vence dia')->assertSee('Vence em');
});

it('marcar uma conta como paga a tira da lista e marca o lançamento', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00'));
    $conta = makeEntry($eliel, ['name' => 'Luz', 'due_on' => '2026-10-07']);

    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-10')->assertSee('Luz')
        ->call('payBill', 'entry', $conta->id, null)->assertDontSeeHtml('data-testid="bill"');

    expect($conta->fresh()->paid)->toBeTrue();
});

it('marcar a fatura como paga a tira da lista', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00'));
    $card = makeCard($eliel);
    $card->update(['due_day' => 10]);
    makeEntry($eliel, ['card_id' => $card->id, 'month' => '2026-09-01', 'amount_cents' => 60000]);

    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-10')->assertSee('Fatura Bradesco')
        ->call('payBill', 'invoice', $card->id, '2026-09')->assertDontSeeHtml('data-testid="bill"');

    expect(CardInvoice::where('card_id', $card->id)->where('month', '2026-09-01')->first()->paid)->toBeTrue();
});

it('o par não marca como paga a conta do outro (403) e na visão do par não há botão', function () {
    [$eliel, $ana] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00'));
    $conta = makeEntry($eliel, ['name' => 'Luz', 'due_on' => '2026-10-07']);

    Livewire::actingAs($ana)->test(HomePage::class)->set('month', '2026-10')->call('payBill', 'entry', $conta->id, null)->assertForbidden();
    expect($conta->fresh()->paid)->toBeFalse();

    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-10')->call('setView', 'par')->assertDontSee('>Pago</button>', false);
});

it('conta do outro parceiro mostra "de Ana" em vez do botão Pago', function () {
    [$eliel, $ana] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00'));
    makeEntry($ana, ['name' => 'Da Ana', 'due_on' => '2026-10-07']);
    makeEntry($eliel, ['name' => 'Do Eliel', 'due_on' => '2026-10-07']);

    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-10')
        ->assertSeeHtml('data-testid="bill-owner">de Ana<')->assertSee('Da Ana')->assertSee('Do Eliel')
        ->assertSeeHtml('>Pago</button>');
});

it('depois de marcar Pago aparece o aviso com Desfazer, e Desfazer devolve a conta à lista', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00'));
    $conta = makeEntry($eliel, ['name' => 'Luz', 'due_on' => '2026-10-07']);

    $tela = Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-10')->call('payBill', 'entry', $conta->id, null)
        ->assertSeeHtml('data-testid="undo"')->assertSee('“Luz” marcada como paga')->assertSee('Extrato com ✓');
    expect($conta->fresh()->paid)->toBeTrue();

    $tela->call('undoPay')->assertDontSeeHtml('data-testid="undo"')->assertSeeHtml('data-testid="bill"');
    expect($conta->fresh()->paid)->toBeFalse();
});

it('desfazer a fatura paga a devolve para a lista', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00'));
    $card = makeCard($eliel);
    $card->update(['due_day' => 10]);
    makeEntry($eliel, ['card_id' => $card->id, 'month' => '2026-09-01', 'amount_cents' => 60000]);

    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-10')->call('payBill', 'invoice', $card->id, '2026-09')
        ->assertSee('Fatura Bradesco” marcada')->call('undoPay')->assertSee('Fatura Bradesco');

    expect(CardInvoice::where('card_id', $card->id)->first()->paid)->toBeFalse();
});
