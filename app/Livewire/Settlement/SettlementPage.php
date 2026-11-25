<?php

namespace App\Livewire\Settlement;

use App\Domain\Settlement as SettlementCalc;
use App\Domain\SettlementResult;
use App\Livewire\Concerns\WithMonthView;
use App\Models\Entry;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Acerto do casal (RF-19): gastos "do casal" (joint) divididos; mostra quem deve a quem.
 */
#[Layout('components.layouts.app')]
#[Title('Acerto do casal · Nossa Finanças')]
class SettlementPage extends Component
{
    use WithMonthView;

    public int $firstPercent = 50;

    public function mount(): void
    {
        $this->firstPercent = auth()->user()->couple->split_first_percent;
    }

    public function updatedFirstPercent(): void
    {
        $this->firstPercent = max(0, min(100, (int) $this->firstPercent));
        auth()->user()->couple->update(['split_first_percent' => $this->firstPercent]);
    }

    public function settle(): void
    {
        $result = $this->calculation()['result'];

        if ($result->amount <= 0) {
            return;
        }

        $record = new Settlement(['month' => $this->monthDate()->toDateString(), 'amount_cents' => $result->amount, 'settled_by' => auth()->id(), 'settled_at' => now()]);
        Gate::authorize('create', Settlement::class);
        $record->couple_id = auth()->user()->couple_id;
        $record->save();
    }

    /** @return array{members: Collection, paid: array<string,int>, result: SettlementResult, total: int} */
    private function calculation(): array
    {
        $members = User::where('couple_id', auth()->user()->couple_id)->orderBy('id')->get();

        // Só 1 membro: tudo para ele, nada a acertar.
        $second = $members->get(1);
        $weights = [$members[0]->name => $this->firstPercent];
        if ($second) {
            $weights[$second->name] = 100 - $this->firstPercent;
        } else {
            $weights[$members[0]->name] = 100;
        }

        $joint = Entry::where('month', $this->monthDate()->toDateString())->where('joint', true)->where('kind', Entry::SAIDA)->get();
        $paid = array_fill_keys(array_keys($weights), 0);
        foreach ($joint as $entry) {
            $payer = $members->firstWhere('id', $entry->paid_by ?? $entry->owner_id);
            if ($payer) {
                $paid[$payer->name] += $entry->amount_cents;
            }
        }

        $already = (int) Settlement::where('month', $this->monthDate()->toDateString())->sum('amount_cents');

        return [
            'members' => $members, 'paid' => $paid, 'total' => array_sum($paid), 'weights' => $weights,
            'result' => SettlementCalc::calculate($paid, $weights, $already),
        ];
    }

    public function render()
    {
        return view('livewire.settlement.settlement-page', $this->calculation());
    }
}
