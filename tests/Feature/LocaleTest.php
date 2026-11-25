<?php

it('usa pt-BR e o fuso de São Paulo', function () {
    expect(config('app.locale'))->toBe('pt_BR')
        ->and(config('app.timezone'))->toBe('America/Sao_Paulo')
        ->and(trans('auth.failed'))->toContain('credenciais');
});

it('tem a tela de login respondendo 200', function () {
    $this->get('/login')->assertOk();
});
