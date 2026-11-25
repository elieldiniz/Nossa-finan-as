<?php

namespace App\Services;

use App\Domain\DueWindow;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Models\Entry;
use App\Support\ViewContext;
use Carbon\CarbonImmutable;

/**
 * Contas a vencer (próximos 7 dias):
 *  - fatura de cada cartão que tem "vence dia": a fatura do mês M vence no dia cadastrado do mês M+1
 *    (como na planilha: "Financeiro de Setembro - Pagamento Outubro"); some quando a fatura está paga;
 *  - lançamentos de saída que não são de cartão, não pagos, com data de vencimento
 *    (do campo "Vence em" ou do dia de cobrança de recorrentes).
 * Compras de cartão não aparecem uma a uma: quem vence é a fatura.
 */
class DueBills
{
    /**
     * @return array<int, array{type: string, id: int, month: ?string, name: string, due: string, days_left: int, amount: int, paid: bool}>
     */
    public function upcoming(CarbonImmutable $today, ViewContext $context, int $days = 7): array
    {
        $today = $today->startOfDay();
        $items = [];

        foreach (Entry::query()->whereNull('card_id')->where('kind', Entry::SAIDA)->where('paid', false)
            ->whereBetween('due_on', [$today->toDateString(), $today->addDays($days)->toDateString()])->get() as $entry) {
            if (! $context->applies($entry->owner_id)) {
                continue;
            }

            $items[] = ['type' => 'entry', 'id' => $entry->id, 'month' => null, 'name' => $entry->name,
                'due' => $entry->due_on->toDateString(), 'amount' => $entry->amount_cents, 'paid' => false];
        }

        foreach (Card::query()->whereNotNull('due_day')->get() as $card) {
            foreach ([-1, 0] as $offset) {
                $invoiceMonth = $today->startOfMonth()->addMonths($offset);
                $dueMonth = $invoiceMonth->addMonth();
                $due = $dueMonth->day(min($card->due_day, $dueMonth->daysInMonth));

                if ($due->lessThan($today) || $due->greaterThan($today->addDays($days))) {
                    continue;
                }

                $total = (int) Entry::query()->where('card_id', $card->id)->where('kind', Entry::SAIDA)
                    ->where('month', $invoiceMonth->toDateString())->get()
                    ->filter(fn (Entry $e) => $context->applies($e->owner_id))->sum('amount_cents');

                $paid = CardInvoice::query()->where('card_id', $card->id)->where('month', $invoiceMonth->toDateString())->where('paid', true)->exists();

                if ($total > 0) {
                    $items[] = ['type' => 'invoice', 'id' => $card->id, 'month' => $invoiceMonth->format('Y-m'), 'name' => 'Fatura '.$card->name,
                        'due' => $due->toDateString(), 'amount' => $total, 'paid' => $paid];
                }
            }
        }

        $window = DueWindow::upcoming($items, $today, $days);
        usort($window, fn ($a, $b) => [$a['due'], $a['name']] <=> [$b['due'], $b['name']]);

        return array_map(fn (array $i) => $i + ['days_left' => (int) $today->diffInDays(CarbonImmutable::parse($i['due']))], $window);
    }
}
