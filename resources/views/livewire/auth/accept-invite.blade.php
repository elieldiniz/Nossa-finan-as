<?php

use App\Services\InvitationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $token = '';
    public string $email = '';
    public string $name = '';
    public string $password = '';
    public string $password_confirmation = '';
    public ?string $error = null;

    public function mount(string $token, InvitationService $service): void
    {
        $this->token = $token;
        $invitation = $service->find($token);

        if ($invitation === null || ! $invitation->isUsable()) {
            $this->error = 'Este convite não é mais válido. Peça um novo ao seu par.';

            return;
        }

        $this->email = $invitation->email;
    }

    public function accept(InvitationService $service): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        try {
            $user = $service->accept($this->token, $data);
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        Auth::login($user);
        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-auth-header title="Você foi convidado(a)" description="Crie sua conta para dividir as finanças com seu par." />

    @if ($error)
        <p role="alert" class="rounded-2xl bg-neg-soft px-4 py-3 text-sm font-bold text-[#8E2F20]" data-testid="invite-error">{{ $error }}</p>
        <a href="{{ route('login') }}" wire:navigate class="nf-btn-ghost w-full">Ir para o login</a>
    @else
        <form wire:submit="accept" class="flex flex-col gap-4" aria-label="Aceitar convite">
            <div><label for="invite-email" class="nf-label">E-mail</label><input id="invite-email" value="{{ $email }}" type="email" disabled class="nf-input bg-zinc-100" /></div>
            <div><label for="name" class="nf-label">Seu nome</label><input wire:model="name" id="name" type="text" required autofocus autocomplete="name" class="nf-input" />@error('name') <p class="mt-1 text-sm font-bold text-neg" role="alert">{{ $message }}</p> @enderror</div>
            <div><label for="password" class="nf-label">Senha</label><input wire:model="password" id="password" type="password" required autocomplete="new-password" class="nf-input" />@error('password') <p class="mt-1 text-sm font-bold text-neg" role="alert">{{ $message }}</p> @enderror</div>
            <div><label for="password_confirmation" class="nf-label">Confirmar senha</label><input wire:model="password_confirmation" id="password_confirmation" type="password" required autocomplete="new-password" class="nf-input" /></div>
            <button type="submit" class="nf-btn w-full" wire:loading.attr="disabled">Criar conta e entrar</button>
        </form>
    @endif
</div>
