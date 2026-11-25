<?php

namespace App\Livewire\Entry;

use App\Livewire\Concerns\WithMonthView;
use App\Models\Card;
use App\Models\Category;
use App\Models\User;
use App\Services\EntryService;
use App\Support\Money;
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

    public bool $joint = false;

    #[Url(as: 'r')]
    public string $repeat = 'none'; // none | monthly | yearly | installments

    public ?int $chargeDay = null;

    public string $endMode = 'never'; // never | date

    public ?string $endsOn = null;

    public ?int $installmentsTotal = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->ownerId = auth()->id();
        $this->chargeDay = (int) now()->format('j');
    }

    public function save(EntryService $entries): mixed
    {
        $this->error = null;

        $this->validate([
            'kind' => ['required', 'in:entrada,saida'],
            'amount' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'categoryId' => ['required', 'integer'],
            'repeat' => ['required', 'in:none,monthly,yearly,installments'],
        ], [], ['amount' => 'valor', 'name' => 'nome', 'categoryId' => 'categoria']);

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
                'owner_id' => $this->ownerId,
                'paid' => $this->paid,
                'joint' => $this->joint,
                'repeat' => $repeat,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return $this->redirectRoute('statement', ['m' => $this->month], navigate: true);
    }

    public function render()
    {
        return view('livewire.entry.entry-form', [
            'categories' => Category::orderBy('position')->get(),
            'cards' => Card::orderBy('name')->get(),
            'members' => User::where('couple_id', auth()->user()->couple_id)->orderBy('id')->get(),
        ]);
    }
}
