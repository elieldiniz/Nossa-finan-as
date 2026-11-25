<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', 'Se existir uma conta com este e-mail, enviaremos o link para redefinir a senha.');
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-auth-header title="Esqueci a senha" description="Informe o seu e-mail e enviaremos um link para criar uma senha nova." />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-4" aria-label="Esqueci a senha">
        <div>
            <label for="email" class="nf-label">E-mail</label>
            <input wire:model="email" id="email" name="email" type="email" required autofocus autocomplete="email" placeholder="voce@email.com" class="nf-input" />
            @error('email') <p class="mt-1 text-sm font-bold text-neg" role="alert">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="nf-btn w-full" wire:loading.attr="disabled">Enviar link</button>
    </form>

    <p class="text-center text-sm text-muted">Lembrou? <a href="{{ route('login') }}" wire:navigate class="font-extrabold text-brand">Voltar para o login</a></p>
</div>
