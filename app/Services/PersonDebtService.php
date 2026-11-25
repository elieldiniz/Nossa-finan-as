<?php

namespace App\Services;

use App\Domain\PersonBalance;
use App\Models\Person;
use App\Models\PersonDebt;
use App\Models\PersonDebtInstallment;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Pendências com pessoas (RF-16, RF-31), com ou sem parcelas.
 */
class PersonDebtService
{
    public function create(User $actor, Person $person, string $direction, int $amountCents, CarbonImmutable $month, ?int $installments = null, ?string $description = null): PersonDebt
    {
        Gate::forUser($actor)->authorize('create', PersonDebt::class);

        if (! in_array($direction, ['receber', 'pagar'], true) || $amountCents <= 0) {
            throw new InvalidArgumentException('Informe se é a pagar ou a receber e um valor maior que zero.');
        }

        return DB::transaction(function () use ($actor, $person, $direction, $amountCents, $month, $installments, $description) {
            $debt = new PersonDebt([
                'person_id' => $person->id,
                'owner_id' => $actor->id,
                'direction' => $direction,
                'description' => $description,
                'amount_cents' => $amountCents,
                'created_month' => $month->startOfMonth()->toDateString(),
            ]);
            $debt->couple_id = $actor->couple_id;
            $debt->save();

            if ($installments !== null && $installments > 1) {
                foreach (Money::split($amountCents, $installments) as $i => $part) {
                    PersonDebtInstallment::create(['person_debt_id' => $debt->id, 'position' => $i + 1, 'amount_cents' => $part]);
                }
            }

            return $debt->load('installments');
        });
    }

    /** Marca uma parcela como paga no mês. Quando a última é paga, a dívida zera a partir do mês seguinte. */
    public function payInstallment(User $actor, PersonDebtInstallment $installment, CarbonImmutable $month): void
    {
        Gate::forUser($actor)->authorize('update', $installment->debt);

        $installment->update(['paid_month' => $month->startOfMonth()->toDateString()]);
    }

    /** Quita uma dívida sem parcelas. */
    public function settle(User $actor, PersonDebt $debt, CarbonImmutable $month): void
    {
        Gate::forUser($actor)->authorize('update', $debt);

        if ($debt->installments()->exists()) {
            throw new InvalidArgumentException('Dívida parcelada: pague as parcelas.');
        }

        $debt->update(['settled_month' => $month->startOfMonth()->toDateString()]);
    }

    /** Saldo da pessoa no mês (receber − pagar), somando as dívidas não quitadas. */
    public function balance(Person $person, CarbonImmutable $month, ?int $ownerId = null): int
    {
        $debts = PersonDebt::with('installments')->where('person_id', $person->id)
            ->when($ownerId !== null, fn ($q) => $q->where('owner_id', $ownerId))->get();

        return PersonBalance::net($debts->map->toBalanceArray()->all(), $month->format('Y-m'));
    }
}
