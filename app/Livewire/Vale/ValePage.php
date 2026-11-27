<?php

namespace App\Livewire\Vale;

use App\Domain\ValeBalance;
use App\Import\OfxCategorizer;
use App\Livewire\Concerns\WithMonthView;
use App\Models\Category;
use App\Models\Vale;
use App\Models\ValeSpend;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Vale por parceiro (RF-17): valor se repete todo mês; a sobra NÃO passa para o mês seguinte.
 */
#[Layout('components.layouts.app')]
#[Title('Vale alimentação · Nossa Finanças')]
class ValePage extends Component
{
    use WithMonthView;

    public string $valeAmount = '';

    public string $spendName = '';

    public string $spendAmount = '';

    public ?int $spendCategoryId = null;

    /** Dia do gasto (aparece no extrato exportado). */
    public ?string $spentOn = null;

    /** A pessoa já escolheu a categoria: a sugestão pelo nome não troca mais. */
    public bool $categoryTouched = false;

    /** Período do extrato a exportar: mes (o aberto) ou tudo. */
    public string $exportScope = 'mes';

    public ?string $error = null;

    public function saveVale(): void
    {
        $this->error = null;

        try {
            $cents = Money::parse($this->valeAmount);
        } catch (InvalidArgumentException) {
            $this->error = 'Informe um valor válido para o vale.';

            return;
        }

        $vale = Vale::where('owner_id', auth()->id())->first() ?? new Vale(['owner_id' => auth()->id()]);
        Gate::authorize($vale->exists ? 'update' : 'create', $vale->exists ? $vale : Vale::class);

        $vale->amount_cents = $cents;
        $vale->couple_id = auth()->user()->couple_id;
        $vale->save();

        $this->valeAmount = '';
    }

    public function updatedSpendName(): void
    {
        if ($this->categoryTouched || trim($this->spendName) === '') {
            return;
        }

        $suggested = OfxCategorizer::suggest($this->spendName);
        $this->spendCategoryId = $suggested === 'Outros' ? null : Category::forKind('saida')->where('name', $suggested)->value('id');
    }

    public function updatedSpendCategoryId(): void
    {
        $this->categoryTouched = true;
    }

    public function addSpend(): void
    {
        $this->error = null;
        $vale = Vale::where('owner_id', auth()->id())->first();

        if ($vale === null) {
            $this->error = 'Defina primeiro o valor do seu vale.';

            return;
        }

        try {
            $cents = Money::parse($this->spendAmount);
        } catch (InvalidArgumentException) {
            $this->error = 'Informe um valor válido para o gasto.';

            return;
        }

        $this->validate(['spendName' => ['required', 'string', 'max:255'], 'spentOn' => ['nullable', 'date'], 'spendCategoryId' => ['nullable', 'integer']], [], ['spendName' => 'nome', 'spentOn' => 'data']);

        $day = $this->spentOn ? CarbonImmutable::parse($this->spentOn) : null;
        if ($day !== null && $day->format('Y-m') !== $this->monthDate()->format('Y-m')) {
            $this->error = 'A data precisa ser de '.$this->monthDate()->translatedFormat('F \d\e Y').', o mês aberto.';

            return;
        }

        $categoryId = $this->spendCategoryId ? Category::forKind('saida')->whereKey($this->spendCategoryId)->value('id') : null;
        Gate::authorize('create', ValeSpend::class);

        $spend = new ValeSpend(['vale_id' => $vale->id, 'owner_id' => auth()->id(), 'name' => $this->spendName, 'category_id' => $categoryId, 'amount_cents' => $cents, 'month' => $this->monthDate()->toDateString(), 'spent_on' => $day?->toDateString()]);
        $spend->couple_id = auth()->user()->couple_id;
        $spend->save();

        $this->reset('spendName', 'spendAmount', 'spendCategoryId', 'categoryTouched');
    }

    public function removeSpend(int $id): void
    {
        $spend = ValeSpend::findOrFail($id);
        Gate::authorize('delete', $spend);
        $spend->delete();
    }

    public function render()
    {
        $context = $this->context();
        $month = $this->monthDate()->toDateString();

        $vales = Vale::with('owner')->get()->filter(fn (Vale $v) => $context->applies($v->owner_id))->values();
        $spends = ValeSpend::with('category')->where('month', $month)->orderBy('id')->get()->groupBy('vale_id');

        $cards = $vales->map(fn (Vale $v) => [
            'vale' => $v,
            'spends' => $spends->get($v->id, collect()),
            'remaining' => ValeBalance::remaining($v->amount_cents, $spends->get($v->id, collect())->pluck('amount_cents')->all()),
            'mine' => $v->owner_id === auth()->id(),
        ]);

        return view('livewire.vale.vale-page', [
            'cards' => $cards,
            'hasOwn' => $vales->contains('owner_id', auth()->id()),
            'categories' => Category::forKind('saida')->orderBy('position')->get(),
            'today' => CarbonImmutable::now()->format('Y-m-d'),
        ]);
    }
}
