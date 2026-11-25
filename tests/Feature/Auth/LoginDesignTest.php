<?php

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

it('a tela de entrar segue o design: marca, campos em português e criar conta', function () {
    $this->get('/login')->assertOk()
        ->assertSee('Nossa', false)->assertSee('Finanças', false)
        ->assertSee('O mês do casal em um só lugar.')
        ->assertSee('E-mail')->assertSee('Senha')->assertSee('Esqueci a senha')->assertSee('Manter conectado')
        ->assertSee('Cada um tem o seu login e os dois veem os mesmos dados.')
        ->assertSee('Criar conta');
});

it('credenciais erradas mostram a mensagem em português', function () {
    Volt::test('auth.login')->set('email', 'ninguem@example.com')->set('password', 'errada')->call('login')
        ->assertHasErrors('email')
        ->assertSee('Estas credenciais não correspondem aos nossos registros.');
});

it('as telas de cadastro e de esqueci a senha usam o mesmo visual', function () {
    $this->get('/register')->assertOk()->assertSee('Criar conta')->assertSee('Seu nome');
    $this->get('/forgot-password')->assertOk()->assertSee('Esqueci a senha')->assertSee('Enviar link');
});

it('o convite inválido mostra aviso em português', function () {
    $url = URL::signedRoute('invitation.accept', ['token' => 'inexistente']);

    $this->get($url)->assertOk()->assertSee('Este convite não é mais válido');
});

it('redefinir senha, confirmar senha e verificar e-mail estão em português', function () {
    $this->get('/reset-password/um-token')->assertOk()->assertSee('Criar nova senha')->assertSee('Salvar nova senha')->assertDontSee('Reset password');

    [$eliel] = makeCouple();
    $eliel->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($eliel)->get('/confirm-password')->assertOk()->assertSee('Confirmar senha')->assertSee('Esta é uma área protegida');
    $this->actingAs($eliel)->get('/verify-email')->assertOk()->assertSee('Confirme o seu e-mail')->assertSee('Reenviar e-mail de verificação')->assertSee('Sair')->assertDontSee('Resend');
});

it('o envio do e-mail de verificação mostra o aviso em português', function () {
    [$eliel] = makeCouple();
    $eliel->forceFill(['email_verified_at' => null])->save();
    Notification::fake();

    Volt::actingAs($eliel)->test('auth.verify-email')->call('sendVerification')->assertSee('Enviamos um novo link');
});
