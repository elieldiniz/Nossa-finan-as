<?php

namespace App\Livewire\Entry;

use App\Livewire\Concerns\WithMonthView;
use App\Models\Card;
use App\Models\Category;
use App\Models\User;
use App\Services\EntryService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tela "Lançar" (+): valor, tipo, categoria, nome, "pago com", "de quem", pago e Repetir (RF-04, RF-11, UI-04).
 */
#[Layout('components.layouts.app')]
#[Title('Lançar · Nossa Finanças')]
class EntryForm extends Component
{
    use WithMonthView;

    public string $kind = 'saida';

    public string $amount = '';

    public string $name = '';

    public ?int $categoryId = null;

    #[Url(as: 'cartao')]
    public ?int $cardId = null;

    public ?int $ownerId = null;

    public bool $paid = false;

    #[Url(as: 'r')]
    public string $repeat = 'none'; // none | monthly | yearly | installments

    public ?int $chargeDay = null;

    public string $endMode = 'never'; // never | date

    public ?string $endsOn = null;

    public ?int $installmentsTotal = null;

    /** Última parcela (Y-m): fica sincronizada com o número de parcelas. */
    public ?string $lastMonth = null;

    /** Conta que já começou: marca como pagas as parcelas/meses anteriores ao mês atual. */
    public bool $markPastPaid = true;

    #[Url(as: 'vence')]
    public ?string $dueOn = null; // "Vence em" (só para saída que não se repete)

    public ?string $error = null;

    /** Identifica este formulário aberto: impede que um toque duplo em "Salvar" crie o lançamento duas vezes. */
    public string $token = '';

    /** Troca de Saída para Entrada (e vice-versa): ajusta categoria e campos que só valem para gasto. */
    public function updatedKind(): void
    {
        $this->resetValidation();
        $this->error = null;

        $fits = Category::forKind($this->kind)->whereKey($this->categoryId)->exists();
        if (! $fits) {
            // Entrada já sugere "Salário" e investimento "Reserva de emergência"; para saída a pessoa escolhe.
            $this->categoryId = match ($this->kind) {
                'entrada' => Category::where('name', 'Salário')->value('id'),
                'aporte' => Category::where('name', 'Reserva de emergência')->value('id'),
                default => null,
            };
        }

        if ($this->kind !== 'saida') {
            $this->cardId = null;
            $this->dueOn = null;

            if ($this->repeat === 'installments') {
                $this->repeat = 'none'; // só saída é parcelada (parcelas a receber ficam em Pessoas)
            }
        }
    }

    public function updatedInstallmentsTotal(): void
    {
        $this->resetValidation('installmentsTotal');
        $this->syncLastMonth();
    }

    public function updatedMonth(): void
    {
        $this->resetValidation('month');
        $this->syncLastMonth();
    }

    public function updatedLastMonth(): void
    {
        $this->resetValidation('installmentsTotal');
        $last = $this->lastMonth ? CarbonImmutable::createFromFormat('!Y-m', $this->lastMonth) : null;

        if (! $last || $last->format('Y-m') !== $this->lastMonth) {
            return;
        }

        $n = $this->monthDate()->diffInMonths($last, false) + 1;
        $this->installmentsTotal = (int) max(2, min(360, $n));
        $this->syncLastMonth(); // se escolheu um mês antes da primeira parcela, volta para o mínimo (2 parcelas)
    }

    private function syncLastMonth(): void
    {
        $this->lastMonth = $this->installmentsTotal && $this->installmentsTotal >= 2
            ? $this->monthDate()->addMonths($this->installmentsTotal - 1)->format('Y-m')
            : null;
    }

    /** "dezembro de 2026": para onde o lançamento vai (null enquanto o mês digitado for inválido). */
    public function monthTarget(): ?string
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m', $this->month)->translatedFormat('F \d\e Y');
    }

    public function startsInThePast(): bool
    {
        return $this->monthDate()->lessThan(CarbonImmutable::now()->startOfMonth());
    }

    /** Valor digitado em centavos (null se ainda não dá para ler). */
    public function amountCents(): ?int
    {
        try {
            $cents = Money::parse($this->amount);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $cents > 0 ? $cents : null;
    }

    public function updatedName(): void
    {
        $this->resetValidation('name');
    }

    public function updatedAmount(): void
    {
        $this->resetValidation('amount');
        $this->error = null;
    }

    public function updatedCategoryId(): void
    {
        $this->resetValidation('categoryId');
    }

    public function mount(): void
    {
        $this->ownerId = auth()->id();
        $this->month = now()->format('Y-m'); // padrão: o mês de verdade (dá para trocar no campo de mês)
        $this->token = (string) Str::uuid();
        $this->chargeDay = (int) now()->format('j');
    }

    public function save(EntryService $entries): mixed
    {
        $this->error = null;

        if ($this->kind === 'entrada') {
            $this->cardId = null; // entrada não passa por cartão nem entra no acerto do casal
        }

        $this->validate([
            'kind' => ['required', 'in:entrada,saida,aporte'],
            'month' => ['required', 'date_format:Y-m'],
            'dueOn' => ['nullable', 'date'],
            'amount' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'categoryId' => ['required', 'integer'],
            'repeat' => ['required', 'in:none,monthly,yearly,installments'],
            'installmentsTotal' => [Rule::requiredIf($this->repeat === 'installments'), 'nullable', 'integer', 'min:2', 'max:360'],
        ], [], ['amount' => 'valor', 'name' => 'nome', 'categoryId' => 'categoria', 'month' => 'mês', 'installmentsTotal' => 'número de parcelas']);

        if (! Cache::add('entry-form-saved:'.$this->token, true, 120)) {
            return $this->redirectRoute('statement', ['m' => $this->month], navigate: true); // já salvo por um toque anterior
        }

        try {
            $cents = Money::parse($this->amount);

            $repeat = $this->repeat === 'none' ? null : array_filter([
                'frequency' => $this->repeat,
                'charge_day' => $this->chargeDay,
                'ends_on' => $this->repeat !== 'installments' && $this->endMode === 'date' ? $this->endsOn : null,
                'installments_total' => $this->repeat === 'installments' ? $this->installmentsTotal : null,
            ], fn ($v) => $v !== null);

            $entries->create(auth()->user(), [
                'kind' => $this->kind,
                'name' => $this->name,
                'amount_cents' => $cents,
                'month' => $this->monthDate()->toDateString(),
                'category_id' => $this->categoryId,
                'card_id' => $this->cardId,
                'due_on' => $this->kind === 'saida' && $this->repeat === 'none' ? $this->dueOn : null,
                'owner_id' => $this->ownerId,
                'paid' => $this->paid,
                'repeat' => $repeat,
                'mark_past_paid' => $repeat !== null && $this->markPastPaid && $this->startsInThePast(),
            ]);
        } catch (InvalidArgumentException $e) {
            Cache::forget('entry-form-saved:'.$this->token);
            $this->error = $e->getMessage();

            return null;
        }

        return $this->redirectRoute('statement', ['m' => $this->month], navigate: true);
    }

    public function render()
    {
        return view('livewire.entry.entry-form', [
            'categories' => Category::forKind($this->kind)->orderBy('position')->get(),
            'cards' => Card::orderBy('name')->get(),
            'members' => User::where('couple_id', auth()->user()->couple_id)->orderBy('id')->get(),
        ]);
    }
}
