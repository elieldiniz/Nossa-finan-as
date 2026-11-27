<?php

namespace App\Services\Vale;

use App\Models\Category;
use App\Models\User;
use App\Models\Vale;
use App\Models\ValeSpend;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Grava no Vale alimentação da pessoa os gastos de um extrato importado (OFX ou PDF).
 * Cada gasto vai para o mês da sua data. O que já existe igual (identificador do extrato, ou mesma data, nome e valor) é pulado.
 */
class ValeImportService
{
    /** @param  array{id: string, name: string, amount: int, date: string}  $line  amount em centavos, positivo */
    public function alreadyImported(Vale $vale, array $line): bool
    {
        if (ValeSpend::query()->where('external_id', $line['id'])->exists()) {
            return true;
        }

        $date = CarbonImmutable::parse($line['date']);

        return ValeSpend::query()->where('vale_id', $vale->id)->where('month', $date->startOfMonth()->toDateString())->where('amount_cents', $line['amount'])
            ->whereRaw('lower(name) = ?', [mb_strtolower($line['name'])])->get()
            ->contains(fn (ValeSpend $s) => $s->statementDate()->toDateString() === $date->toDateString());
    }

    /**
     * @param  array<int, array{id: string, name: string, amount: int, date: string, category_id: ?int}>  $lines
     * @param  int|null  $valeAmount  valor do vale (todo mês): obrigatório se a pessoa ainda não tem vale; se informado, atualiza
     * @return array{created: int, skipped: int, months: int}
     */
    public function import(User $actor, array $lines, ?int $valeAmount = null): array
    {
        Gate::forUser($actor)->authorize('create', ValeSpend::class);

        return DB::transaction(function () use ($actor, $lines, $valeAmount) {
            $vale = Vale::where('owner_id', $actor->id)->first();

            if ($vale === null) {
                if (! $valeAmount || $valeAmount <= 0) {
                    throw new InvalidArgumentException('Informe o valor do seu vale alimentação (o que entra todo mês) para importar.');
                }

                $vale = new Vale(['owner_id' => $actor->id, 'amount_cents' => $valeAmount]);
                $vale->couple_id = $actor->couple_id;
                $vale->save();
            } elseif ($valeAmount && $valeAmount > 0 && $valeAmount !== $vale->amount_cents) {
                Gate::forUser($actor)->authorize('update', $vale);
                $vale->update(['amount_cents' => $valeAmount]);
            }

            $allowed = Category::forKind('saida')->pluck('id')->flip();
            $created = 0;
            $skipped = 0;
            $months = [];

            foreach ($lines as $line) {
                if ($this->alreadyImported($vale, $line)) {
                    $skipped++;

                    continue;
                }

                $date = CarbonImmutable::parse($line['date']);
                $spend = new ValeSpend([
                    'vale_id' => $vale->id, 'owner_id' => $actor->id, 'name' => $line['name'], 'amount_cents' => $line['amount'],
                    'month' => $date->startOfMonth()->toDateString(), 'spent_on' => $date->toDateString(),
                    'category_id' => ! empty($line['category_id']) && $allowed->has((int) $line['category_id']) ? (int) $line['category_id'] : null,
                    'external_id' => $line['id'],
                ]);
                $spend->couple_id = $actor->couple_id;
                $spend->save();

                $created++;
                $months[$date->format('Y-m')] = true;
            }

            return ['created' => $created, 'skipped' => $skipped, 'months' => count($months)];
        });
    }
}
