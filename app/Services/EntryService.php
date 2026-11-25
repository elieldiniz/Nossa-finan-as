<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Recurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Criar, alterar e apagar lançamentos (RF-03, RF-04, RF-06).
 */
class EntryService
{
    /**
     * @param  array<string, mixed>  $data  kind, name, amount_cents, month, category_id, card_id?, owner_id?, paid?, joint?, repeat?
     *                                      repeat: ['frequency' => monthly|yearly|installments, 'charge_day'?, 'ends_on'?, 'installments_total'?]
     */
    public function create(User $actor, array $data): Entry
    {
        Gate::forUser($actor)->authorize('create', Entry::class);

        $month = CarbonImmutable::parse($data['month'])->startOfMonth();
        $ownerId = (int) ($data['owner_id'] ?? $actor->id);
        $this->assertOwnerInCouple($actor, $ownerId);

        if (($data['amount_cents'] ?? 0) <= 0) {
            throw new InvalidArgumentException('O valor precisa ser maior que zero.');
        }

        $category = Category::withoutGlobalScopes()->where('couple_id', $actor->couple_id)->find($data['category_id'] ?? 0)
            ?? throw new InvalidArgumentException('Escolha uma categoria.');
        $cardId = isset($data['card_id']) ? $this->cardId($actor, (int) $data['card_id']) : null;

        return DB::transaction(function () use ($actor, $data, $month, $ownerId, $category, $cardId) {
            $paid = (bool) ($data['paid'] ?? false);
            $recurrenceId = null;
            $label = null;
            $dueOn = $data['due_on'] ?? null;

            if (! empty($data['repeat'])) {
                $repeat = $data['repeat'];
                $recurrence = new Recurrence([
                    'owner_id' => $ownerId,
                    'category_id' => $category->id,
                    'card_id' => $cardId,
                    'kind' => $data['kind'],
                    'name' => $data['name'],
                    'amount_cents' => $data['amount_cents'],
                    'frequency' => $repeat['frequency'],
                    'starts_on' => $month->toDateString(),
                    'charge_day' => $repeat['charge_day'] ?? null,
                    'ends_on' => $repeat['ends_on'] ?? null,
                    'installments_total' => $repeat['installments_total'] ?? null,
                    'joint' => (bool) ($data['joint'] ?? false),
                ]);
                $recurrence->couple_id = $actor->couple_id;
                $recurrence->schedule(); // valida a regra (ex.: parcelado sem nº de parcelas)
                $recurrence->save();

                $recurrenceId = $recurrence->id;
                $schedule = $recurrence->schedule();
                $label = $schedule->installmentLabel($month->year, $month->month);
                $dueOn = $schedule->dateIn($month->year, $month->month)?->toDateString() ?? $dueOn;
            }

            $entry = new Entry([
                'owner_id' => $ownerId,
                'kind' => $data['kind'],
                'name' => $data['name'],
                'amount_cents' => $data['amount_cents'],
                'month' => $month->toDateString(),
                'due_on' => $dueOn,
                'category_id' => $category->id,
                'card_id' => $cardId,
                'paid' => $paid,
                'joint' => (bool) ($data['joint'] ?? false),
                'paid_by' => $paid ? ($data['paid_by'] ?? $actor->id) : null,
                'recurrence_id' => $recurrenceId,
                'installment_label' => $label,
            ]);
            $entry->save();

            return $entry;
        });
    }

    public function setPaid(User $actor, Entry $entry, bool $paid): Entry
    {
        Gate::forUser($actor)->authorize('update', $entry);

        $entry->update(['paid' => $paid, 'paid_by' => $paid ? $actor->id : null]);

        return $entry;
    }

    /** Altera um lançamento que NÃO é recorrente. Recorrentes passam por RecurrenceScopeService. */
    public function update(User $actor, Entry $entry, array $changes): Entry
    {
        Gate::forUser($actor)->authorize('update', $entry);

        if ($entry->recurrence_id !== null) {
            throw new InvalidArgumentException('Este lançamento é recorrente: escolha o escopo da alteração.');
        }

        $entry->update($changes);

        return $entry;
    }

    public function delete(User $actor, Entry $entry): void
    {
        Gate::forUser($actor)->authorize('delete', $entry);

        if ($entry->recurrence_id !== null) {
            throw new InvalidArgumentException('Este lançamento é recorrente: escolha o escopo da exclusão.');
        }

        $entry->delete();
    }

    private function assertOwnerInCouple(User $actor, int $ownerId): void
    {
        if (! User::where('id', $ownerId)->where('couple_id', $actor->couple_id)->exists()) {
            throw new InvalidArgumentException('O dono precisa ser um dos dois do casal.');
        }
    }

    private function cardId(User $actor, int $id): int
    {
        return Card::withoutGlobalScopes()->where('couple_id', $actor->couple_id)->findOrFail($id)->id;
    }
}
