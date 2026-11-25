<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-auth-header title="Entrar" />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-4" aria-label="Entrar">
        <div>
            <label for="email" class="nf-label">E-mail</label>
            <input wire:model="email" id="email" name="email" type="email" required autofocus autocomplete="email" placeholder="voce@email.com" class="nf-input" />
            @error('email') <p class="mt-1 text-sm font-bold text-neg" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="nf-label">Senha</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="mb-1 text-xs font-bold text-brand">Esqueci a senha</a>
                @endif
            </div>
            <input wire:model="password" id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" class="nf-input" />
            @error('password') <p class="mt-1 text-sm font-bold text-neg" role="alert">{{ $message }}</p> @enderror
        </div>

        <label class="flex min-h-11 items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model="remember" class="size-5 accent-brand" /> Manter conectado</label>

        <button type="submit" class="nf-btn w-full" wire:loading.attr="disabled">Entrar</button>
    </form>

    <p class="text-center text-sm leading-relaxed text-muted">
        Cada um tem o seu login e os dois veem os mesmos dados.<br>
        Ainda não tem conta? <a href="{{ route('register') }}" wire:navigate class="font-extrabold text-brand">Criar conta</a>
    </p>
</div>
