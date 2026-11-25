<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-auth-header title="Confirmar senha" description="Esta é uma área protegida. Confirme a sua senha para continuar." />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="confirmPassword" class="flex flex-col gap-4" aria-label="Confirmar senha">
        <div>
            <label for="password" class="nf-label">Senha</label>
            <input wire:model="password" id="password" name="password" type="password" required autofocus autocomplete="current-password" placeholder="••••••••" class="nf-input" />
            @error('password') <p class="mt-1 text-sm font-bold text-neg" role="alert">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="nf-btn w-full" wire:loading.attr="disabled">Confirmar</button>
    </form>
</div>
