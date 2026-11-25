<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-auth-header title="Confirme o seu e-mail" description="Clique no link que acabamos de enviar para o seu e-mail para verificar a conta." />

    @if (session('status') == 'verification-link-sent')
        <p role="status" class="rounded-2xl bg-pos-soft px-4 py-3 text-sm font-bold text-pos" data-testid="link-sent">Enviamos um novo link de verificação para o e-mail do cadastro.</p>
    @endif

    <div class="flex flex-col gap-3">
        <button type="button" wire:click="sendVerification" class="nf-btn w-full" wire:loading.attr="disabled">Reenviar e-mail de verificação</button>
        <button type="button" wire:click="logout" class="nf-btn-ghost w-full">Sair</button>
    </div>
</div>
