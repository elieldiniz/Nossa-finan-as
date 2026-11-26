<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DueBillsTodayMail;
use App\Services\DueNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class AvisarVencimentos extends Command
{
    protected $signature = 'contas:avisar';

    protected $description = 'Avisa por e-mail as contas que vencem hoje (o sininho do app é atualizado junto)';

    public function handle(DueNotifier $notifier): int
    {
        $sent = 0;

        foreach (User::query()->whereNotNull('couple_id')->get() as $user) {
            Auth::setUser($user); // as consultas são do casal da pessoa
            $notifier->syncToday($user);

            if (! $user->notify_email) {
                continue;
            }

            $pending = $user->unreadNotifications()->get()->filter(fn ($n) => ! ($n->data['emailed'] ?? false) && str_starts_with($n->data['key'] ?? '', 'due:'));

            if ($pending->isEmpty()) {
                continue;
            }

            $user->notify(new DueBillsTodayMail($pending->map(fn ($n) => ['name' => $n->data['name'], 'amount' => $n->data['amount']])->values()->all()));

            foreach ($pending as $n) {
                $n->data = array_merge($n->data, ['emailed' => true]);
                $n->save();
            }

            $sent++;
        }

        Auth::forgetUser();
        $this->info("{$sent} e-mail(s) enviado(s).");

        return self::SUCCESS;
    }
}
