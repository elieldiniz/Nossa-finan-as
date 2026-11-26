<?php

namespace App\Services;

use App\Models\CardInvoice;
use App\Models\Entry;
use App\Models\User;
use App\Notifications\DueBillToday;
use App\Support\ViewContext;
use Carbon\CarbonImmutable;

/**
 * Avisos de "vence hoje" (sininho do app e e-mail).
 * Cada pessoa é avisada das contas dela e das faturas de cartão (que são do casal). Um aviso por conta e por dia.
 * Precisa de usuário autenticado (as consultas são do casal dele).
 */
class DueNotifier
{
    /** @return array<int, array<string, mixed>> contas que vencem hoje, ainda não pagas, que esta pessoa deve receber */
    public function billsToday(User $user, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();

        return array_values(array_filter(
            app(DueBills::class)->forMonth($today, ViewContext::for($user, ViewContext::NOS), $today),
            fn (array $b) => ! $b['paid'] && $b['due'] === $today->toDateString() && ($b['owner_id'] === null || $b['owner_id'] === $user->id),
        ));
    }

    /** Cria os avisos de hoje que ainda não existem; devolve quantos criou. */
    public function syncToday(User $user, ?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();
        $have = $user->notifications()->where('created_at', '>=', $today->toDateTimeString())->get()->pluck('data.key')->filter()->flip();
        $created = 0;

        foreach ($this->billsToday($user, $today) as $bill) {
            $key = "due:{$bill['type']}:{$bill['id']}:{$bill['month']}:{$today->toDateString()}";

            if ($have->has($key)) {
                continue;
            }

            $user->notify(new DueBillToday($bill, $key));
            $created++;
        }

        return $created;
    }

    /** Conta já paga (em qualquer tela): o aviso some, como se tivesse sido lido. */
    public function resolvePaid(User $user): void
    {
        foreach ($user->unreadNotifications()->get() as $notification) {
            $data = $notification->data;

            $paid = ($data['type'] ?? null) === 'invoice'
                ? CardInvoice::query()->where('card_id', $data['id'])->where('month', ($data['month'] ?? '').'-01')->where('paid', true)->exists()
                : (Entry::query()->whereKey($data['id'] ?? 0)->value('paid') ?? true);

            if ($paid) {
                $notification->markAsRead();
            }
        }
    }
}
