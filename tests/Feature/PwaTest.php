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

it('usa o ícone do app em todos os tamanhos (normal e maskable) e nos favicons', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect(collect($manifest['icons'])->pluck('purpose')->unique()->sort()->values()->all())->toBe(['any', 'maskable']);

    foreach (['icon-192.png' => 192, 'icon-512.png' => 512, 'icon-maskable-192.png' => 192, 'icon-maskable-512.png' => 512, 'apple-touch-icon.png' => 180, 'favicon-32.png' => 32] as $file => $side) {
        [$w, $h] = getimagesize(public_path("icons/{$file}"));
        expect([$w, $h])->toBe([$side, $side]);
    }

    [$eliel] = makeCouple();
    $this->actingAs($eliel)->get(route('dashboard'))->assertSee('/icons/favicon-32.png', false)->assertSee('/icons/apple-touch-icon.png', false);
});

it('o ícone do app aparece no menu lateral e na tela de entrar, no lugar do logo antigo', function () {
    [$eliel] = makeCouple();

    $this->actingAs($eliel)->get(route('dashboard'))->assertSee('<img src="/icons/icon-192.png"', false)->assertDontSee('bg-lime"></span>', false);
    auth()->logout();
    $this->get('/login')->assertSee('<img src="/icons/icon-192.png"', false)->assertDontSee('Laravel Starter Kit');
});
