<?php

use App\Livewire\Statement\StatementPage;
use App\Models\Entry;
use Livewire\Livewire;

it('lançar uma saída simples leva no máximo 3 interações a partir do mês (digitar + tocar em adicionar)', function () {
    [$eliel] = makeCouple();

    $tela = Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09'); // 0: o mês já está aberto

    $tela->set('quick', '50 mercado');   // 1: digitar a frase
    $tela->call('quickAdd');             // 2: tocar em adicionar

    expect(Entry::first())->toMatchArray(['name' => 'mercado', 'amount_cents' => 5000, 'kind' => 'saida']);
    // Nenhuma terceira interação foi necessária; o teto de UI-04 é 3.
});
