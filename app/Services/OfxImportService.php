<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Entry;
use App\Models\Recurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Grava as compras de uma fatura de cartão (OFX) como lançamentos do cartão.
 * Parcelas ("Parcela 2/5") viram parcelado, continuando nos meses seguintes. Nada é gravado duas vezes:
 * a mesma transação (identificador do banco) ou a mesma parcela no mesmo mês é pulada.
 */
class OfxImportService
{
    /**
     * @param  array<int, array{id: string, name: string, amount: int, installment: ?array{0: int, 1: int}, category_id: int, date?: ?string}>  $lines  amount em centavos, positivo
     * @return array{created: int, skipped: int, recurrences: int}
     */
    public function import(User $actor, Card $card, CarbonImmutable $month, int $ownerId, array $lines): array
    {
        $month = $month->startOfMonth();
        $counts = ['created' => 0, 'skipped' => 0, 'recurrences' => 0];

        DB::transaction(function () use ($actor, $card, $month, $ownerId, $lines, &$counts) {
            foreach ($lines as $line) {
                if ($this->alreadyImported($card, $month, $line)) {
                    $counts['skipped']++;

                    continue;
                }

                $recurrenceId = null;
                $label = null;

                if (! empty($line['installment'])) {
                    [$k, $n] = $line['installment'];
                    $recurrence = new Recurrence([
                        'owner_id' => $ownerId, 'category_id' => $line['category_id'], 'card_id' => $card->id, 'kind' => 'saida', 'name' => $line['name'],
                        'amount_cents' => $line['amount'], 'frequency' => 'installments', 'starts_on' => $month->subMonths($k - 1)->toDateString(), 'installments_total' => $n,
                    ]);
                    $recurrence->couple_id = $actor->couple_id;
                    $recurrence->save();
                    $recurrenceId = $recurrence->id;
                    $label = $k.'/'.$n;
                    $counts['recurrences']++;
                }

                $entry = new Entry([
                    'owner_id' => $ownerId, 'kind' => Entry::SAIDA, 'name' => $line['name'], 'amount_cents' => $line['amount'], 'month' => $month->toDateString(),
                    'category_id' => $line['category_id'], 'card_id' => $card->id, 'paid' => false, 'recurrence_id' => $recurrenceId,
                    'installment_label' => $label, 'external_id' => $line['id'], 'purchased_on' => $line['date'] ?? null,
                ]);
                $entry->couple_id = $actor->couple_id;
                $entry->save();
                $counts['created']++;
            }
        });

        return $counts;
    }

    /** Já existe? Mesmo identificador do banco, ou a mesma parcela (nome, valor e "k/n") deste cartão no mês. */
    public function alreadyImported(Card $card, CarbonImmutable $month, array $line): bool
    {
        if (Entry::query()->where('external_id', $line['id'])->exists()) {
            return true;
        }

        if (empty($line['installment'])) {
            return false;
        }

        return Entry::query()->where('card_id', $card->id)->where('month', $month->startOfMonth()->toDateString())->where('name', $line['name'])
            ->where('amount_cents', $line['amount'])->where('installment_label', $line['installment'][0].'/'.$line['installment'][1])->exists();
    }
}
