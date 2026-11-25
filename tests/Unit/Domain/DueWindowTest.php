<?php

use App\Domain\DueWindow;
use Carbon\CarbonImmutable;

it('inclui vencimento em até 7 dias e exclui 8 dias e itens pagos', function () {
    $hoje = CarbonImmutable::parse('2026-09-10');

    $itens = [
        ['name' => 'hoje', 'due' => '2026-09-10', 'paid' => false],
        ['name' => 'em 7 dias', 'due' => '2026-09-17', 'paid' => false],
        ['name' => 'em 8 dias', 'due' => '2026-09-18', 'paid' => false],
        ['name' => 'pago', 'due' => '2026-09-12', 'paid' => true],
        ['name' => 'ontem', 'due' => '2026-09-09', 'paid' => false],
    ];

    $nomes = array_column(DueWindow::upcoming($itens, $hoje), 'name');

    expect($nomes)->toBe(['hoje', 'em 7 dias']);
});
