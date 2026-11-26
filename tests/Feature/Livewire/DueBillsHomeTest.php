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
        ->assertSee('vence hoje')->assertSee('vence amanhã')->assertSee('vence dia 09/10')->assertSee('R$ 1.500,00');
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
        ->assertSeeHtml('data-testid="undo"')->assertSee('“Luz” marcada como paga')->assertSeeHtml('data-testid="bill-paid"');
    expect($conta->fresh()->paid)->toBeTrue();

    $tela->call('unpayBill', 'entry', $conta->id, null)->assertSeeHtml('data-testid="bill"')->assertDontSeeHtml('data-testid="bill-paid"');
    expect($conta->fresh()->paid)->toBeFalse();
});

it('desfazer a fatura paga a devolve para a lista', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00'));
    $card = makeCard($eliel);
    $card->update(['due_day' => 10]);
    makeEntry($eliel, ['card_id' => $card->id, 'month' => '2026-09-01', 'amount_cents' => 60000]);

    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-10')->call('payBill', 'invoice', $card->id, '2026-09')
        ->assertSee('Fatura Bradesco” marcada')->call('unpayBill', 'invoice', $card->id, '2026-09')->assertSeeHtml('data-testid="bill"');

    expect(CardInvoice::where('card_id', $card->id)->first()->paid)->toBeFalse();
});

it('mostra tudo que vence no mês, não só os próximos 7 dias, com contador de pagas', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-11-26 09:00'));
    makeEntry($eliel, ['name' => 'Aluguel', 'month' => '2026-11-01', 'due_on' => '2026-11-05', 'paid' => true]);
    makeEntry($eliel, ['name' => 'Internet', 'month' => '2026-11-01', 'due_on' => '2026-11-20']);
    makeEntry($eliel, ['name' => 'Luz', 'month' => '2026-11-01', 'due_on' => '2026-11-30']);
    makeEntry($eliel, ['name' => 'Parcela dezembro', 'month' => '2026-12-01', 'due_on' => '2026-12-25']);

    $nov = Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-11')
        ->assertSee('Internet')->assertSee('venceu 20/11 (atrasada)')->assertSee('Luz')->assertSee('vence dia 30/11')
        ->assertSee('1 de 3 pagas')->assertSeeHtml('data-testid="bill-paid"')->assertDontSee('Parcela dezembro');

    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-12')
        ->assertSee('Parcela dezembro')->assertSee('vence dia 25/12')->assertDontSee('Internet')->assertSee('0 de 1 pagas');
});

it('mês sem contas e mês todo pago têm mensagens diferentes', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-11-26 09:00'));
    makeEntry($eliel, ['name' => 'Luz', 'month' => '2026-11-01', 'due_on' => '2026-11-30', 'paid' => true]);

    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-11')->assertSee('Tudo pago neste mês');
    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2027-05')->assertSee('Nenhuma conta com vencimento neste mês');
});
