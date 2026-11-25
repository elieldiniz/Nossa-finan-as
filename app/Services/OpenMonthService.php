<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Recurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * "Mês pronto" (RF-10, RF-11): ao abrir um mês, fixos, assinaturas e parcelas do casal entram sozinhos.
 * Idempotente: abrir o mês várias vezes não duplica nada, e ocorrência apagada ("só este mês") não volta.
 * Roda como sistema, para os dois donos (não passa pela Policy do usuário).
 */
class OpenMonthService
{
    /** @return int quantidade de ocorrências criadas agora */
    public function open(int $coupleId, CarbonImmutable $month): int
    {
        $month = $month->startOfMonth();
        $created = 0;

        $recurrences = Recurrence::withoutGlobalScopes()->where('couple_id', $coupleId)
            ->where('starts_on', '<=', $month->endOfMonth()->toDateString())
            ->get();

        if ($recurrences->isEmpty()) {
            return 0;
        }

        $existing = Entry::withoutGlobalScopes()->withTrashed()
            ->whereIn('recurrence_id', $recurrences->pluck('id'))
            ->where('month', $month->toDateString())
            ->pluck('recurrence_id')->flip();

        DB::transaction(function () use ($recurrences, $existing, $month, &$created) {
            foreach ($recurrences as $recurrence) {
                $schedule = $recurrence->schedule();

                if (! $schedule->occursIn($month->year, $month->month) || $existing->has($recurrence->id)) {
                    continue;
                }

                $entry = new Entry([
                    'owner_id' => $recurrence->owner_id,
                    'kind' => $recurrence->kind,
                    'name' => $recurrence->name,
                    'amount_cents' => $recurrence->amount_cents,
                    'month' => $month->toDateString(),
                    'due_on' => $schedule->dateIn($month->year, $month->month)?->toDateString(),
                    'category_id' => $recurrence->category_id,
                    'card_id' => $recurrence->card_id,
                    'joint' => $recurrence->joint,
                    'paid' => false,
                    'recurrence_id' => $recurrence->id,
                    'installment_label' => $schedule->installmentLabel($month->year, $month->month),
                ]);
                $entry->couple_id = $recurrence->couple_id;
                $entry->saveQuietly();
                $created++;
            }
        });

        return $created;
    }
}
