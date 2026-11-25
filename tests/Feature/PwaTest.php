<?php

it('o manifesto instalável existe e é válido', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['lang'])->toBe('pt-BR')
        ->and($manifest['start_url'])->toBe('/dashboard')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512');

    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue();
    }
});

it('o service worker existe, não guarda gravações e limpa o cache ao Sair', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("request.method !== 'GET'")
        ->and($sw)->toContain('clear-cache')
        ->and($sw)->toContain('addEventListener(\'fetch\'');

    expect(file_get_contents(resource_path('js/pwa.js')))->toContain('/logout')->toContain("postMessage('clear-cache')");
});

it('o layout referencia o manifesto e avisa quando está sem internet', function () {
    [$eliel] = makeCouple();

    $this->actingAs($eliel)->get(route('dashboard'))->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('/manifest.webmanifest', false)
        ->assertSee('é necessário internet para lançar', false);
});

it('a tela inicial sem login manda para o login e logada para o painel', function () {
    $this->get('/')->assertRedirect('/login');

    [$eliel] = makeCouple();
    $this->actingAs($eliel)->get('/')->assertRedirect('/dashboard');
});
