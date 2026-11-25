<?php

use App\Livewire\Home\HomePage;
use Livewire\Livewire;

/** Reaproveita o cenário da planilha de MonthSummaryTest (Setembro). */
it('mostra o caso da planilha: −R$ 485,00 com rótulo e sinal', function () {
    [$eliel] = cenarioPlanilha();

    Livewire::actingAs($eliel)->test(HomePage::class, [])
        ->set('month', '2026-09')
        ->assertSee('−R$ 485,00')
        ->assertSee('Saiu mais do que entrou')
        ->assertSee('Bradesco')
        ->assertSee('Madrinha')
        ->assertSee('R$ 2.250,00');
});

it('mostra os 7 blocos do painel', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(HomePage::class)
        ->assertSeeHtml('data-testid="closing-value"')
        ->assertSeeHtml('data-testid="income"')
        ->assertSeeHtml('data-testid="expense"')
        ->assertSeeHtml('data-testid="spent"')
        ->assertSeeHtml('data-testid="cards-list"')
        ->assertSeeHtml('data-testid="people-list"')
        ->assertSeeHtml('data-testid="vale-card"')
        ->assertSeeHtml('data-testid="recent"');
});

it('as 3 visões trocam o recorte e a do par é somente leitura', function () {
    [$eliel] = cenarioPlanilha();

    $tela = Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-09');

    $tela->call('setView', 'eu')->assertSee('R$ 600,00')->assertDontSeeHtml('data-testid="readonly-badge"')
        ->call('setView', 'par')->assertSeeHtml('data-testid="readonly-badge"')->assertSee('Somente leitura')
        ->call('setView', 'nos')->assertSee('−R$ 485,00');
});

it('navega entre meses identificados por mês e ano', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-09')
        ->assertSee('Setembro de 2026')
        ->call('nextMonth')->assertSee('Outubro de 2026')
        ->call('previousMonth')->call('previousMonth')->assertSee('Agosto de 2026');
});

it('o mês seguinte já vem preenchido com os fixos', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    fixo($eliel, valor: 30000, mes: '2026-09-01');

    Livewire::test(HomePage::class)->set('month', '2026-10')->assertSee('Fixo');
});
