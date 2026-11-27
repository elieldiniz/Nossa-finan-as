<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Recurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Editar e excluir recorrentes com 3 escopos (RF-07, RF-08):
 *  - THIS      só este mês
 *  - FOLLOWING este e os próximos meses (os anteriores ficam como estão)
 *  - ALL       todos, inclusive os anteriores: altera ou apaga o histórico, inclusive o já pago (e recalcula).
 */
class RecurrenceScopeService
{
    public const THIS = 'this';

    public const FOLLOWING = 'following';

    public const ALL = 'all';

    private const EDITABLE = ['name', 'amount_cents', 'category_id', 'card_id', 'joint'];

    /** Só fazem sentido para um mês específico (vencimento e pago). */
    private const ONLY_THIS = ['due_on', 'paid', 'paid_by'];

    public function update(User $actor, Entry $entry, array $changes, string $scope): void
    {
        Gate::forUser($actor)->authorize('update', $entry);
        $recurrence = $this->recurrenceOf($entry);
        $onlyThis = array_intersect_key($changes, array_flip(self::ONLY_THIS));
        $changes = array_intersect_key($changes, array_flip(self::EDITABLE));

        DB::transaction(function () use ($entry, $recurrence, $changes, $onlyThis, $scope) {
            match ($scope) {
                self::THIS => $entry->update($changes + $onlyThis),
                self::FOLLOWING => $this->updateFollowing($entry, $recurrence, $changes),
                self::ALL => $this->updateAll($recurrence, $changes),
                default => throw new InvalidArgumentException("Escopo desconhecido: {$scope}"),
            };

            // vencimento e "pago" são deste mês: valem para o lançamento que está sendo editado em qualquer escopo
            // (antes eram descartados em silêncio quando o escopo não era "Só este mês")
            if ($scope !== self::THIS && $onlyThis !== []) {
                $entry->update($onlyThis);
            }
        });
    }

    /**
     * Muda a regra da conta toda (dia de cobrança, data final, número de parcelas) e acerta os meses:
     * meses que deixam de existir e ainda não foram pagos são removidos; os novos são criados; vencimentos e
     * "parcela x/y" são recalculados. Meses já pagos nunca são apagados.
     *
     * @param  array{charge_day?: ?int, ends_on?: ?string, installments_total?: ?int}  $schedule
     */
    public function reschedule(User $actor, Entry $entry, array $schedule): void
    {
        Gate::forUser($actor)->authorize('update', $entry);
        $recurrence = $this->recurrenceOf($entry);
        $values = array_intersect_key($schedule, array_flip(['charge_day', 'ends_on', 'installments_total']));

        $dayChanged = isset($values['charge_day']) && $values['charge_day'] !== $recurrence->charge_day;

        DB::transaction(function () use ($recurrence, $values, $dayChanged) {
            $recurrence->fill($values);
            $rule = $recurrence->schedule(); // valida a regra (ex.: parcelado precisa de nº de parcelas)
            $recurrence->save();

            foreach (Entry::withTrashed()->where('recurrence_id', $recurrence->id)->get() as $occurrence) {
                $y = $occurrence->month->year;
                $m = $occurrence->month->month;

                if (! $rule->occursIn($y, $m)) {
                    if (! $occurrence->paid) {
                        $occurrence->forceDelete();
                    }

                    continue;
                }

                // o vencimento só é recalculado quando o dia de cobrança mudou (senão preserva ajustes feitos num mês)
                $occurrence->update(['installment_label' => $rule->installmentLabel($y, $m)] + ($dayChanged ? ['due_on' => $rule->dateIn($y, $m)?->toDateString() ?? $occurrence->due_on] : []));
            }

            app(OpenMonthService::class)->fillRecurrence($recurrence);
        });
    }

    public function delete(User $actor, Entry $entry, string $scope): void
    {
        Gate::forUser($actor)->authorize('delete', $entry);
        $recurrence = $this->recurrenceOf($entry);

        DB::transaction(function () use ($entry, $recurrence, $scope) {
            match ($scope) {
                self::THIS => $entry->delete(),
                self::FOLLOWING => $this->deleteFollowing($entry, $recurrence),
                self::ALL => $recurrence->delete(), // as ocorrências vão junto (cascata no banco)
                default => throw new InvalidArgumentException("Escopo desconhecido: {$scope}"),
            };
        });
    }

    private function updateFollowing(Entry $entry, Recurrence $recurrence, array $changes): void
    {
        $recurrence->update($changes);
        Entry::where('recurrence_id', $recurrence->id)->where('month', '>=', $entry->month->toDateString())->update($changes);
    }

    private function updateAll(Recurrence $recurrence, array $changes): void
    {
        $recurrence->update($changes);
        Entry::withTrashed()->where('recurrence_id', $recurrence->id)->update($changes); // o "pago" não muda
    }

    private function deleteFollowing(Entry $entry, Recurrence $recurrence): void
    {
        if ($entry->month->lessThanOrEqualTo($recurrence->starts_on->startOfMonth())) {
            $recurrence->delete(); // apagar desde o primeiro mês é apagar tudo

            return;
        }

        $recurrence->update(['ends_on' => $entry->month->subMonth()->endOfMonth()->toDateString()]);
        Entry::where('recurrence_id', $recurrence->id)->where('month', '>=', $entry->month->toDateString())->delete();
    }

    private function recurrenceOf(Entry $entry): Recurrence
    {
        return Recurrence::withoutGlobalScopes()->where('couple_id', $entry->couple_id)->find($entry->recurrence_id)
            ?? throw new InvalidArgumentException('Este lançamento não é recorrente.');
    }
}
