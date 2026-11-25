<?php

it('todas as rotas de dados exigem sessão', function (string $route) {
    $this->get(route($route))->assertRedirect('/login');
})->with([
    'dashboard', 'statement', 'entry.create', 'cards', 'people', 'vale', 'recurring',
    'goals', 'charts', 'report', 'categories', 'more', 'import', 'invite',
]);

it('um lançamento não abre sem login', function () {
    [$eliel] = makeCouple();
    $item = makeEntry($eliel);

    $this->get(route('entry.show', $item->id))->assertRedirect('/login');
});
