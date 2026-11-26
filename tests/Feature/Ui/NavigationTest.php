<?php

use App\Livewire\More\MorePage;

it('a barra de baixo tem os 5 itens: Início, Extrato, +, Cartões e Mais', function () {
    [$eliel] = makeCouple();

    $this->actingAs($eliel)->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Início', 'Extrato', 'aria-label="Lançar"', 'Cartões', 'Mais']);
});

it('"Mais" abre os 9 itens e cada um leva a uma tela que responde', function () {
    [$eliel] = makeCouple();
    $resposta = $this->actingAs($eliel)->get(route('more'))->assertOk();

    foreach (MorePage::ITEMS as [$route, $label]) {
        $resposta->assertSee($label);
        $this->actingAs($eliel)->get(route($route))->assertOk();
    }

    expect(count(MorePage::ITEMS))->toBe(9);
});

it('o menu lateral do PC alcança todas as áreas', function () {
    [$eliel] = makeCouple();
    $html = $this->actingAs($eliel)->get(route('dashboard'))->getContent();

    foreach (['dashboard', 'statement', 'cards', 'people', 'vale', 'recurring', 'goals', 'charts', 'report', 'categories', 'import', 'import.ofx', 'invite'] as $route) {
        expect($html)->toContain('href="'.route($route).'"');
    }
});
