<?php

use App\Models\User;
use App\Services\CoupleBootstrap;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);
        app(CoupleBootstrap::class)->createFor($user);

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-auth-header title="Criar conta" description="Você cria a conta e depois convida seu par para usar junto." />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-4" aria-label="Criar conta">
        <div>
            <label for="name" class="nf-label">Seu nome</label>
            <input wire:model="name" id="name" name="name" type="text" required autofocus autocomplete="name" placeholder="Como você quer ser chamado(a)" class="nf-input" />
            @error('name') <p class="mt-1 text-sm font-bold text-neg" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="nf-label">E-mail</label>
            <input wire:model="email" id="email" name="email" type="email" required autocomplete="email" placeholder="voce@email.com" class="nf-input" />
            @error('email') <p class="mt-1 text-sm font-bold text-neg" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="nf-label">Senha</label>
            <input wire:model="password" id="password" name="password" type="password" required autocomplete="new-password" placeholder="Mínimo de 8 caracteres" class="nf-input" />
            @error('password') <p class="mt-1 text-sm font-bold text-neg" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="nf-label">Confirmar senha</label>
            <input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="nf-input" />
        </div>

        <button type="submit" class="nf-btn w-full" wire:loading.attr="disabled">Criar conta</button>
    </form>

    <p class="text-center text-sm text-muted">Já tem conta? <a href="{{ route('login') }}" wire:navigate class="font-extrabold text-brand">Entrar</a></p>
</div>
