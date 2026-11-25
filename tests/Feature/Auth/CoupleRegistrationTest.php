<?php

use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Livewire\Volt\Volt;

it('registrar cria o casal e as 19 categorias iniciais (10 de saída ou ambas, 4 de entrada e 5 de investimento)', function () {
    Volt::test('auth.register')
        ->set('name', 'Eliel')->set('email', 'eliel@example.com')
        ->set('password', 'senha-forte-123')->set('password_confirmation', 'senha-forte-123')
        ->call('register')->assertHasNoErrors();

    $user = User::where('email', 'eliel@example.com')->first();

    expect(Couple::count())->toBe(1)
        ->and($user->couple_id)->not->toBeNull()
        ->and(Category::withoutGlobalScopes()->where('couple_id', $user->couple_id)->count())->toBe(19);
});

it('Sair encerra a sessão', function () {
    [$eliel] = makeCouple();

    $this->actingAs($eliel)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
