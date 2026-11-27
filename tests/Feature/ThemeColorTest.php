<?php

use App\Livewire\Settings\ThemeColor;
use App\Support\Theme;
use Livewire\Livewire;

function contraste(string $a, string $b): float
{
    $lum = function (string $hex) {
        [$r, $g, $b] = array_map(fn ($i) => hexdec(substr(ltrim($hex, '#'), $i, 2)) / 255, [0, 2, 4]);
        $f = fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $f($r) + 0.7152 * $f($g) + 0.0722 * $f($b);
    };
    [$x, $y] = [$lum($a), $lum($b)];

    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

it('todas as cores têm contraste suficiente: texto branco na cor principal e a cor principal sobre o fundo suave', function () {
    foreach (Theme::PALETTES as $key => $p) {
        expect(contraste('#FFFFFF', $p['brand']))->toBeGreaterThanOrEqual(4.5, "{$key}: branco sobre brand")
            ->and(contraste($p['brand'], $p['soft']))->toBeGreaterThanOrEqual(4.5, "{$key}: brand sobre soft")
            ->and(contraste('#FFFFFF', $p['dark']))->toBeGreaterThanOrEqual(7, "{$key}: branco sobre dark");
    }
});

it('o padrão é verde e a cor verde é exatamente a de antes', function () {
    expect(Theme::DEFAULT)->toBe('verde')->and(Theme::palette('verde')['brand'])->toBe('#14614F')->and(Theme::palette('inexistente'))->toBe(Theme::palette('verde'));
});

it('o perfil mostra as cores, com a atual marcada', function () {
    [$eliel] = makeCouple();

    $this->actingAs($eliel)->get(route('settings.profile'))->assertOk()->assertSee('Cor do app')->assertSeeHtml('data-testid="theme-azul"');

    Livewire::actingAs($eliel)->test(ThemeColor::class)->assertSet('color', 'verde')->assertSeeHtml('aria-checked="true"');
});

it('escolher uma cor salva na conta, recarrega a página e todas as telas passam a usá-la', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ThemeColor::class)->call('choose', 'azul')->assertRedirect(route('settings.profile'));

    expect($eliel->fresh()->theme_color)->toBe('azul');

    foreach (['dashboard', 'statement', 'cards', 'settings.profile'] as $rota) {
        $this->actingAs($eliel->fresh())->get(route($rota))->assertOk()
            ->assertSee('--color-brand:#1D5FA8', false)->assertSee('name="theme-color" content="#1D5FA8"', false);
    }
});

it('cor inválida é recusada e nada muda', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ThemeColor::class)->call('choose', 'verde-limão-neon')->assertHasErrors('color')->assertNoRedirect();

    expect($eliel->fresh()->theme_color)->toBe('verde');
});

it('a cor é de cada pessoa: o parceiro continua com a dele', function () {
    [$eliel, $ana] = makeCouple();
    Livewire::actingAs($eliel)->test(ThemeColor::class)->call('choose', 'roxo');

    $this->actingAs($ana)->get(route('dashboard'))->assertSee('--color-brand:#14614F', false);
    $this->actingAs($eliel->fresh())->get(route('dashboard'))->assertSee('--color-brand:#6B3FA0', false);
});

it('o aplicativo não tem mais verde fixo nas telas (tudo usa as cores do tema)', function () {
    foreach (['resources/views', 'app/Livewire'] as $dir) {
        $hits = shell_exec("grep -rn -i -E '1B7A63|5BB89A|rgba\\(20, ?97, ?79' ".escapeshellarg(base_path($dir)).' 2>/dev/null');
        expect(trim((string) $hits))->toBe('');
    }
});

it('a tela de login usa a última cor lembrada no navegador', function () {
    $this->withCookie('nf_theme', 'rosa')->get('/login')->assertOk()->assertSee('--color-brand:#B83280', false);
});

it('sem cookie, a tela de login sai verde', function () {
    $this->get('/login')->assertOk()->assertSee('--color-brand:#14614F', false);
});
