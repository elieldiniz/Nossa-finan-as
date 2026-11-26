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
        return $this->openRange($coupleId, $month, 1);
    }

    /**
     * Gera os lançamentos recorrentes de vários meses de uma vez: o mês de partida e os próximos ($months - 1).
     * É o que faz uma conta "todo mês" ou parcelada já aparecer em todos os meses seguintes, sem ninguém abri-los.
     *
     * @param  int|null  $onlyRecurrenceId  gera só para esta recorrência (ao criar uma nova)
     * @return int quantidade de ocorrências criadas agora
     */
    public function openRange(int $coupleId, CarbonImmutable $from, int $months = 12, ?int $onlyRecurrenceId = null): int
    {
        $from = $from->startOfMonth();
        $last = $from->addMonths(max($months, 1) - 1);
        $created = 0;

        $recurrences = Recurrence::withoutGlobalScopes()->where('couple_id', $coupleId)
            ->where('starts_on', '<=', $last->endOfMonth()->toDateString())
            ->when($onlyRecurrenceId, fn ($q) => $q->whereKey($onlyRecurrenceId))
            ->get();

        if ($recurrences->isEmpty()) {
            return 0;
        }

        $existing = Entry::withoutGlobalScopes()->withTrashed()
            ->whereIn('recurrence_id', $recurrences->pluck('id'))
            ->whereBetween('month', [$from->toDateString(), $last->toDateString()])
            ->get(['recurrence_id', 'month'])
            ->mapWithKeys(fn (Entry $e) => [$e->recurrence_id.'|'.$e->month->format('Y-m') => true]);

        DB::transaction(function () use ($recurrences, $existing, $from, $months, &$created) {
            for ($i = 0; $i < max($months, 1); $i++) {
                $month = $from->addMonths($i);

                foreach ($recurrences as $recurrence) {
                    $schedule = $recurrence->schedule();

                    if (! $schedule->occursIn($month->year, $month->month) || $existing->has($recurrence->id.'|'.$month->format('Y-m'))) {
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
            }
        });

        return $created;
    }
}
