<?php

namespace App\Services;

use App\Domain\CardInvoice;
use App\Domain\ClosingResult;
use App\Domain\MonthClosing;
use App\Domain\PersonBalance;
use App\Domain\ValeBalance;
use App\Models\Card;
use App\Models\CardInvoice as CardInvoiceModel;
use App\Models\Entry;
use App\Models\PersonDebt;
use App\Models\User;
use App\Models\Vale;
use App\Models\ValeSpend;
use App\Support\Money;
use App\Support\ViewContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Monta tudo o que as telas mostram de um mês, no recorte escolhido (Nós, Eu ou Par).
 * Poucas consultas (RNF-01): lançamentos, cartões, pessoas e vale.
 */
class MonthSummary
{
    /**
     * @return array{
     *   month: CarbonImmutable, view: ViewContext, entries: Collection, cards: array, people: array,
     *   vale: array, closing: ClosingResult
     * }
     */
    public function build(User $viewer, CarbonImmutable $month, string $view = ViewContext::NOS): array
    {
        $month = $month->startOfMonth();
        $context = ViewContext::for($viewer, $view);
        $monthKey = $month->format('Y-m');

        $all = Entry::query()->with('category')->where('month', $month->toDateString())->orderBy('due_on')->orderBy('id')->get()
            ->filter(fn (Entry $e) => $context->applies($e->owner_id))->values();

        $plain = $all->whereNull('card_id')->values();
        $onCards = $all->whereNotNull('card_id')->where('kind', Entry::SAIDA);

        $paidInvoices = CardInvoiceModel::query()->where('month', $month->toDateString())->where('paid', true)->pluck('card_id')->flip();

        $cards = [];
        foreach (Card::query()->orderBy('id')->get() as $card) {
            $lines = $onCards->where('card_id', $card->id)->values();
            $total = CardInvoice::total($lines->pluck('amount_cents')->all());
            $cards[] = ['card' => $card, 'lines' => $lines, 'total' => $total, 'expense' => CardInvoice::asExpense($total), 'paid' => $paidInvoices->has($card->id)];
        }

        $people = [];
        foreach (PersonDebt::with(['person', 'installments'])->get()->filter(fn ($d) => $context->applies($d->owner_id))->groupBy('person_id') as $debts) {
            $people[] = [
                'person' => $debts->first()->person,
                'net' => PersonBalance::net($debts->map->toBalanceArray()->all(), $monthKey),
            ];
        }

        $vale = $this->vale($context, $month);

        $closing = MonthClosing::calculate(
            $plain->map(fn (Entry $e) => ['type' => $e->kind, 'amount' => $e->amount_cents])->all(),
            array_column($cards, 'total'),
            array_column($people, 'net'),
            $vale['amount'],
            $vale['spent'],
        );

        return [
            'month' => $month,
            'view' => $context,
            'entries' => $plain,
            'cards' => $cards,
            'people' => $people,
            'vale' => $vale,
            'closing' => $closing,
        ];
    }

    /** O vale é por parceiro; em "Nós" soma os dois. A sobra não passa para o mês seguinte. O valor é entrada e o gasto é saída no saldo. */
    private function vale(ViewContext $context, CarbonImmutable $month): array
    {
        $vales = Vale::query()->get()->filter(fn (Vale $v) => $context->applies($v->owner_id));
        $spends = ValeSpend::query()->where('month', $month->toDateString())->get()
            ->filter(fn (ValeSpend $s) => $context->applies($s->owner_id));

        $amount = (int) $vales->sum('amount_cents');
        $spent = (int) $spends->sum('amount_cents');

        return [
            'amount' => $amount, 'spent' => $spent, 'remaining' => ValeBalance::remaining($amount, $spends->pluck('amount_cents')->all()),
            'spends' => $spends->sortBy('id')->map(fn (ValeSpend $x) => ['id' => $x->id, 'name' => $x->name, 'amount' => $x->amount_cents])->values()->all(),
        ];
    }

    /**
     * Linhas do extrato/painel: lançamentos avulsos, uma saída por cartão (automática) e o saldo de cada pessoa.
     *
     * @param  array<string, mixed>  $summary  resultado de build()
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(array $summary, bool $withVale = false): Collection
    {
        $rows = $summary['entries']->map(fn (Entry $e) => [
            'source' => 'entry', 'id' => $e->id, 'name' => $e->name,
            'sub' => ($e->kind === Entry::APORTE ? 'Investimento · ' : '').($e->category?->name ?? 'Sem categoria').($e->installment_label ? ' · parcela '.$e->installment_label : '').($e->recurrence_id ? ' · recorrente' : ''),
            'signed' => $e->kind === Entry::ENTRADA ? $e->amount_cents : -$e->amount_cents,
            'kind' => $e->kind,
            'paid' => $e->paid, 'owner_id' => $e->owner_id, 'recurring' => $e->recurrence_id !== null,
        ]);

        foreach ($summary['cards'] as $c) {
            if ($c['expense'] !== null) {
                $lines = $c['lines']->map(fn (Entry $e) => [
                    'id' => $e->id, 'name' => $e->name, 'amount' => $e->amount_cents,
                    'sub' => ($e->purchased_on ? $e->purchased_on->format('d/m').' · ' : '').($e->category?->name ?? 'Sem categoria').($e->installment_label ? ' · parcela '.$e->installment_label : ''),
                ])->all();

                $rows->push(['source' => 'card', 'id' => $c['card']->id, 'name' => 'Cartão '.$c['card']->name, 'sub' => $this->cardPurchases($lines),
                    'signed' => -$c['total'], 'kind' => 'saida', 'paid' => $c['paid'], 'owner_id' => null, 'recurring' => false, 'lines' => $lines]);
            }
        }

        // O vale: o valor entra como entrada e o gasto como saída no saldo; aqui mostra o restante e os gastos dentro.
        $vale = $summary['vale'];
        if ($withVale && ($vale['amount'] > 0 || $vale['spends'] !== [])) {
            $rows->push(['source' => 'vale', 'id' => 0, 'name' => 'Vale', 'kind' => 'vale', 'paid' => false, 'owner_id' => null, 'recurring' => false,
                'sub' => 'Gastou '.Money::format($vale['spent']).' de '.Money::format($vale['amount']).($vale['spends'] === [] ? '' : ' · '.implode(', ', array_slice(array_column($vale['spends'], 'name'), 0, 2)).(count($vale['spends']) > 2 ? ' e mais '.(count($vale['spends']) - 2) : '')),
                'signed' => $vale['remaining'], 'lines' => array_map(fn ($x) => ['id' => $x['id'], 'name' => $x['name'], 'amount' => $x['amount'], 'sub' => 'Gasto do vale'], $vale['spends']),
                'vale' => $vale]);
        }

        foreach ($summary['people'] as $p) {
            if ($p['net'] !== 0) {
                $rows->push(['source' => 'person', 'id' => $p['person']->id, 'name' => 'Saldo '.$p['person']->name, 'sub' => 'Pessoas · automático',
                    'signed' => $p['net'], 'kind' => $p['net'] >= 0 ? 'entrada' : 'saida', 'paid' => false, 'owner_id' => null, 'recurring' => false]);
            }
        }

        return $rows->values();
    }

    /** "SHEIN" · "SHEIN, Uber" · "SHEIN, Uber e mais 2": o que tem dentro da fatura, sem precisar abrir. */
    private function cardPurchases(array $lines): string
    {
        $names = array_values(array_unique(array_column($lines, 'name')));

        return match (true) {
            count($names) <= 2 => implode(', ', $names),
            default => $names[0].', '.$names[1].' e mais '.(count($names) - 2),
        };
    }
}
