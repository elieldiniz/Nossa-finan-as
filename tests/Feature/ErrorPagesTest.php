<?php

it('páginas de erro vêm em português dizendo o que houve', function (int $status, string $texto) {
    $this->get('/nao-existe-essa-rota')->assertNotFound()->assertSee('Página não encontrada')->assertSee('Ir para o Início');

    expect(view('errors.'.$status)->render())->toContain($texto)->not->toContain('Server Error');
})->with([
    [403, 'só de quem lançou'],
    [404, 'Não achamos'],
    [419, 'sessão expirou'],
    [429, 'muitas tentativas'],
    [500, 'pode não ter sido salvo'],
    [503, 'manutenção'],
]);

it('o app carrega o aviso global de erro e o tratamento de falhas das ações', function () {
    [$eliel] = makeCouple();

    $this->actingAs($eliel)->get('/dashboard')->assertSee('data-testid="toast"', false)->assertSee('nf-toast', false);
    expect(file_get_contents(base_path('resources/js/errors.js')))->toContain('403:')->toContain('419:')->toContain('preventDefault');
});
