<?php

namespace App\Livewire\People;

use App\Domain\PersonBalance;
use App\Livewire\Concerns\WithMonthView;
use App\Models\Person;
use App\Models\PersonDebt;
use App\Models\PersonDebtInstallment;
use App\Services\PersonDebtService;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pessoas e pendências a pagar/receber, com ou sem parcelas (RF-16, RF-31).
 */
#[Layout('components.layouts.app')]
#[Title('Pessoas · Nossa Finanças')]
class PeoplePage extends Component
{
    use WithMonthView;

    public string $personName = '';

    public ?int $personId = null;

    public string $direction = 'receber';

    public string $amount = '';

    public ?int $installments = null;

    public string $description = '';

    public ?string $error = null;

    public function addPerson(): void
    {
        Gate::authorize('create', Person::class);
        $this->validate(['personName' => ['required', 'string', 'max:255']], [], ['personName' => 'nome']);

        $person = new Person(['name' => trim($this->personName)]);
        $person->couple_id = auth()->user()->couple_id;
        $person->save();

        $this->reset('personName');
    }

    public function addDebt(PersonDebtService $service): void
    {
        $this->error = null;

        if ($this->context()->isReadOnly()) {
            $this->error = 'Você está na visão do seu par (somente leitura).';

            return;
        }

        try {
            $service->create(
                auth()->user(), Person::findOrFail($this->personId), $this->direction, Money::parse($this->amount),
                $this->monthDate(), $this->installments, $this->description ?: null,
            );
            $this->reset('amount', 'installments', 'description');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function payInstallment(int $id, PersonDebtService $service): void
    {
        $service->payInstallment(auth()->user(), PersonDebtInstallment::with('debt')->findOrFail($id), $this->monthDate());
    }

    public function settle(int $debtId, PersonDebtService $service): void
    {
        $service->settle(auth()->user(), PersonDebt::findOrFail($debtId), $this->monthDate());
    }

    public function removePerson(int $id): void
    {
        $person = Person::findOrFail($id);
        Gate::authorize('delete', $person);
        $person->delete();
    }

    public function render()
    {
        $monthKey = $this->monthDate()->format('Y-m');
        $context = $this->context();

        $people = Person::with(['debts' => fn ($q) => $q->with('installments')])->orderBy('name')->get()->map(function (Person $person) use ($context, $monthKey) {
            $debts = $person->debts->filter(fn (PersonDebt $d) => $context->applies($d->owner_id));

            return [
                'person' => $person,
                'debts' => $debts->values(),
                'net' => PersonBalance::net($debts->map->toBalanceArray()->all(), $monthKey),
                'pay' => $debts->where('direction', 'pagar')->sum(fn ($d) => PersonBalance::outstanding($d->toBalanceArray(), $monthKey)),
                'receive' => $debts->where('direction', 'receber')->sum(fn ($d) => PersonBalance::outstanding($d->toBalanceArray(), $monthKey)),
            ];
        });

        return view('livewire.people.people-page', ['people' => $people, 'monthKey' => $monthKey]);
    }
}
