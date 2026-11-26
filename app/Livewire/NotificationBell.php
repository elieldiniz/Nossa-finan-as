<?php

namespace App\Livewire;

use App\Services\DueNotifier;
use Livewire\Component;

/** Sininho: avisos de "vence hoje". Tocar no aviso o dá como visto (some) e leva para Contas a vencer. */
class NotificationBell extends Component
{
    public function open(string $id): mixed
    {
        $notification = auth()->user()->notifications()->whereKey($id)->first();
        $notification?->markAsRead();

        return $this->redirectRoute('dashboard', navigate: true);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function toggleEmail(): void
    {
        $user = auth()->user();
        $user->forceFill(['notify_email' => ! $user->notify_email])->save();
    }

    public function render()
    {
        $user = auth()->user();
        $notifier = app(DueNotifier::class);

        $notifier->resolvePaid($user);
        $notifier->syncToday($user);

        return view('livewire.notification-bell', [
            'items' => $user->unreadNotifications()->latest()->limit(20)->get(),
            'emailOn' => (bool) $user->notify_email,
        ]);
    }
}
