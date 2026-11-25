<?php

namespace App\Livewire\Invite;

use App\Services\InvitationService;
use InvalidArgumentException;
use Livewire\Component;

class InvitePartner extends Component
{
    public string $email = '';

    public ?string $message = null;

    public ?string $error = null;

    public function send(InvitationService $service): void
    {
        $this->validate(['email' => ['required', 'email', 'max:255']]);
        $this->message = $this->error = null;

        try {
            $service->invite(auth()->user(), $this->email);
            $this->message = 'Convite enviado para '.$this->email.'.';
            $this->email = '';
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.invite.invite-partner', [
            'partner' => auth()->user()->partner(),
        ])->layout('components.layouts.app', ['title' => 'Convidar par']);
    }
}
