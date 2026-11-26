<?php

use App\Livewire\Statement\StatementPage;
use App\Models\Entry;
use Livewire\Livewire;

it('"50 mercado" cria saída de 50,00 em "Outros", não paga', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')
        ->set('quick', '50 mercado')->call('quickAdd')->assertHasNoErrors()->assertSee('mercado');

    $entry = Entry::withoutGlobalScopes()->first();
    expect($entry->amount_cents)->toBe(5000)->and($entry->paid)->toBeFalse()
        ->and($entry->category->name)->toBe('Outros')->and($entry->kind)->toBe('saida');
});

it('frase fora do formato é rejeitada sem gravar nada', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('quick', 'mercado')->call('quickAdd')
        ->assertSeeHtml('data-testid="quick-error"');

    expect(Entry::withoutGlobalScopes()->count())->toBe(0);
});

it('filtra Pendentes e Pagos e busca por nome', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['name' => 'Aluguel', 'paid' => true]);
    makeEntry($eliel, ['name' => 'Padaria', 'paid' => false]);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')
        ->call('setFilter', 'pending')->assertSee('Padaria')->assertDontSee('Aluguel')
        ->call('setFilter', 'paid')->assertSee('Aluguel')->assertDontSee('Padaria')
        ->call('setFilter', 'all')->set('search', 'alu')->assertSee('Aluguel')->assertDontSee('Padaria');
});

it('o dono marca como pago e isso persiste', function () {
    [$eliel] = makeCouple();
    $item = makeEntry($eliel);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->call('togglePaid', $item->id);

    expect($item->fresh()->paid)->toBeTrue();
});

it('o par não marca pago no item do outro: ação direta é recusada e o item fica igual', function () {
    [$eliel, $ana] = makeCouple();
    $item = makeEntry($eliel);

    Livewire::actingAs($ana)->test(StatementPage::class)->set('month', '2026-09')
        ->call('togglePaid', $item->id)->assertForbidden();

    expect($item->fresh()->paid)->toBeFalse();
});

it('na visão do par não dá para lançar rápido', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(StatementPage::class)->call('setView', 'par')
        ->set('quick', '10 teste')->call('quickAdd');

    expect(Entry::withoutGlobalScopes()->count())->toBe(0);
});

it('o Extrato mostra a palavra do status e explica o que aconteceu ao marcar e desmarcar', function () {
    [$eliel] = makeCouple();
    $conta = makeEntry($eliel, ['name' => 'Luz', 'due_on' => '2026-09-20']);

    $tela = Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')
        ->assertSeeHtml('data-testid="status">A pagar<');

    $tela->call('togglePaid', $conta->id)->assertSeeHtml('data-testid="status">✓ Pago<')->assertSee('“Luz” marcada como paga ✓')->assertSee('Para desfazer, toque no ✓ de novo');

    $tela->call('togglePaid', $conta->id)->assertSeeHtml('data-testid="status">A pagar<')->assertSee('voltou para “a pagar”')->assertSee('volta para Contas a vencer');
});

it('entrada e investimento usam palavras próprias', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['kind' => 'entrada', 'name' => 'Salário', 'category_id' => categoryOf($eliel->couple_id, 'Salário')->id]);
    makeEntry($eliel, ['kind' => 'aporte', 'name' => 'CDB', 'category_id' => categoryOf($eliel->couple_id, 'Renda fixa')->id, 'paid' => true]);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->assertSee('A receber')->assertSee('✓ Aplicado');
});

it('na visão "Nós" cada um vê os lançamentos e parcelas dos dois', function () {
    [$eliel, $ana] = makeCouple();
    makeEntry($eliel, ['name' => 'Parcela do Eliel', 'installment_label' => '1/3']);
    makeEntry($ana, ['name' => 'Parcela da Ana', 'installment_label' => '2/4']);

    foreach ([$eliel, $ana] as $quem) {
        Livewire::actingAs($quem)->test(StatementPage::class)->set('month', '2026-09')
            ->assertSee('Parcela do Eliel')->assertSee('Parcela da Ana');
    }

    Livewire::actingAs($ana)->test(StatementPage::class)->set('month', '2026-09')->call('setView', 'eu')
        ->assertSee('Parcela da Ana')->assertDontSee('Parcela do Eliel');
});
