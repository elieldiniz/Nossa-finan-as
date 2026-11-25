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

<div class="flex flex-col gap-6">
    <x-auth-header title="Você foi convidado(a)" description="Crie sua conta para dividir as finanças com seu par" />

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" heading="{{ $error }}" />
    @else
        <form wire:submit="accept" class="flex flex-col gap-6">
            <flux:input value="{{ $email }}" label="E-mail" type="email" disabled />
            <flux:input wire:model="name" label="Seu nome" type="text" required autofocus autocomplete="name" />
            <flux:input wire:model="password" label="Senha" type="password" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" label="Confirmar senha" type="password" required autocomplete="new-password" viewable />
            <flux:button type="submit" variant="primary" class="w-full">Criar conta e entrar</flux:button>
        </form>
    @endif
</div>
