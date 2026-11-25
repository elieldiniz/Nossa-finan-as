<?php

use App\Livewire\Entry\EntryForm;
use App\Livewire\Goals\GoalsPage;
use App\Livewire\Home\HomePage;
use App\Livewire\Recurring\RecurringPage;
use App\Livewire\Statement\StatementPage;
use App\Models\Entry;
use App\Models\User;
use App\Services\CoupleBootstrap;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

it('mensagens de validação aparecem em português, nunca como chave crua', function () {
    $erros = [
        Validator::make(['d' => 40], ['d' => ['integer', 'between:1,31']])->errors()->first(),
        Validator::make(['m' => 'abc'], ['m' => ['date_format:Y-m']])->errors()->first(),
        Validator::make(['e' => 'ABC@X.COM'], ['e' => ['lowercase']])->errors()->first(),
        Validator::make(['p' => '123'], ['p' => [Password::min(8)->letters()->numbers()]])->errors()->first(),
        Validator::make(['p' => 'abcdefgh'], ['p' => [Password::min(8)->letters()->numbers()]])->errors()->first(),
    ];

    foreach ($erros as $erro) {
        expect($erro)->not->toStartWith('validation.')->and($erro)->not->toBeEmpty();
    }
    expect($erros[0])->toContain('entre 1 e 31')->and($erros[4])->toContain('número');
});

it('os e-mails de redefinir senha e de confirmar e-mail saem em português', function () {
    [$eliel] = makeCouple();

    $reset = (new ResetPassword('token-x'))->toMail($eliel)->render();
    $verify = (new VerifyEmail)->toMail($eliel)->render();

    expect((string) $reset)->toContain('Olá!')->toContain('Redefinir senha')->toContain('Atenciosamente')->not->toContain('Hello!')
        ->and((string) $verify)->toContain('Confirmar e-mail')->not->toContain('Verify Email Address');
    expect((new ResetPassword('t'))->toMail($eliel)->subject)->toBe('Redefinição de senha');
});

it('as configurações (perfil e senha) estão em português', function () {
    [$eliel] = makeCouple();

    $this->actingAs($eliel)->get('/settings/profile')->assertOk()
        ->assertSee('Configurações')->assertSee('Perfil')->assertSee('Atualize o seu nome e o seu e-mail')->assertSee('Excluir conta')
        ->assertDontSee('Update your name')->assertDontSee('Delete Account')->assertDontSee('Appearance');

    $this->actingAs($eliel)->get('/settings/password')->assertOk()
        ->assertSee('Trocar senha')->assertSee('Senha atual')->assertSee('Nova senha')->assertDontSee('Update password');
});

it('recorrente de entrada (salário): mostra com + e não entra na soma dos gastos recorrentes', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    $tela = Livewire::test(RecurringPage::class)->set('month', '2026-09');
    $tela->set('name', 'Streaming')->set('amount', '40,00')->set('categoryId', categoryOf($eliel->couple_id, 'Assinatura')->id)->call('save')->assertHasNoErrors();

    $tela->set('kind', 'entrada')
        ->assertSet('categoryId', categoryOf($eliel->couple_id, 'Salário')->id)
        ->assertDontSee('Mercado')->assertSee('Renda extra')->assertDontSee('id="rc"', false)->assertSee('Recebe no dia')
        ->set('name', 'Salário')->set('amount', '3.000,00')->call('save')->assertHasNoErrors();

    $tela->assertSeeHtml('data-testid="total">R$ 40,00<')   // só os gastos
        ->assertSeeHtml('data-testid="income-total"')->assertSee('+R$ 3.000,00');
});

it('em Metas só aparecem categorias de gasto (não Salário nem Renda extra)', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(GoalsPage::class)->assertSee('Mercado')->assertSee('Moradia')->assertSee('Outros')
        ->assertDontSee('Salário')->assertDontSee('Renda extra')->assertDontSee('Rendimentos');
});

it('em Entrada não existe "Parcelado" e uma entrada parcelada vira "não repete"', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('repeat', 'installments')->assertSee('Parcelado')
        ->set('kind', 'entrada')->assertDontSee('Parcelado')->assertSet('repeat', 'none')->assertSee('Todo ano');
});

it('o seletor de visão e a saudação usam só o primeiro nome', function () {
    $eliel = User::factory()->create(['name' => 'Eliel Diniz']);
    app(CoupleBootstrap::class)->createFor($eliel);
    $eliel = $eliel->fresh();
    $ana = User::factory()->create(['name' => 'Ana Claudia Souza', 'couple_id' => $eliel->couple_id]);

    expect($eliel->firstName())->toBe('Eliel');

    Livewire::actingAs($eliel)->test(HomePage::class)->assertSee('Olá, Eliel')->assertDontSee('Olá, Eliel Diniz')
        ->assertSeeHtml('>Eliel</button>')->assertSeeHtml('>Ana</button>')->assertDontSee('Ana Claudia Souza');
});

it('o filtro do extrato chama-se "Pagos e recebidos"', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['kind' => 'entrada', 'category_id' => categoryOf($eliel->couple_id, 'Salário')->id, 'paid' => true]);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->assertSee('Pagos e recebidos')
        ->call('setFilter', 'paid')->assertSee('Item');

    expect(Entry::withoutGlobalScopes()->count())->toBe(1);
});

it('não existe mais a tela de aparência (o app é só claro)', function () {
    [$eliel] = makeCouple();

    $this->actingAs($eliel)->get('/settings/appearance')->assertNotFound();
});
