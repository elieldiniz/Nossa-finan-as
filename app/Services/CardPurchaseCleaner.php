<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Entry;
use App\Models\Recurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Exclusão em massa das compras de UM cartão: de um mês ou de todos os meses.
 *
 *  - só as compras do próprio usuário (as do parceiro ficam, e são contadas à parte);
 *  - a exclusão é "suave" (as compras ficam guardadas), então dá para desfazer;
 *  - parceladas e fixas vêm de uma regra que gera um lançamento por mês: com "parar as parceladas e fixas" a regra é
 *    encerrada (não volta mais nos meses seguintes); sem isso, só os lançamentos existentes somem.
 */
class CardPurchaseCleaner
{
    /** @return array{count: int, total: int, series: int, others: int} */
    public function preview(User $actor, Card $card, ?CarbonImmutable $month): array
    {
        $mine = $this->mine($actor, $card, $month)->get(['amount_cents', 'recurrence_id']);

        return [
            'count' => $mine->count(),
            'total' => (int) $mine->sum('amount_cents'),
            'series' => $mine->whereNotNull('recurrence_id')->pluck('recurrence_id')->unique()->count(),
            'others' => $this->scope($card, $month)->where('owner_id', '!=', $actor->id)->count(),
        ];
    }

    /**
     * @return array{deleted: int, total: int, undo: array{entries: array<int, int>, recurrences: array<int, ?string>}}
     */
    public function delete(User $actor, Card $card, ?CarbonImmutable $month, bool $stopSeries): array
    {
        Gate::forUser($actor)->authorize('update', $card);

        return DB::transaction(function () use ($actor, $card, $month, $stopSeries) {
            $targets = $this->mine($actor, $card, $month)->get();
            $undo = ['entries' => [], 'recurrences' => []];

            if ($stopSeries) {
                $recurrences = Recurrence::query()->where('owner_id', $actor->id)->whereIn('id', $targets->pluck('recurrence_id')->filter()->unique())->get();

                foreach ($recurrences as $recurrence) {
                    // do mês aberto em diante (ou a série toda): some o que já foi gerado e a regra para de gerar
                    $from = $month ?? $recurrence->starts_on->startOfMonth();
                    $undo['recurrences'][$recurrence->id] = $recurrence->ends_on?->toDateString();
                    $recurrence->update(['ends_on' => $from->subMonth()->endOfMonth()->toDateString()]);

                    foreach (Entry::query()->where('recurrence_id', $recurrence->id)->where('owner_id', $actor->id)->where('month', '>=', $from->toDateString())->get(['id']) as $later) {
                        $undo['entries'][] = $later->id;
                    }
                }
            }

            $ids = collect($undo['entries'])->merge($targets->pluck('id'))->unique()->values();
            Entry::query()->whereIn('id', $ids)->where('owner_id', $actor->id)->delete();
            $undo['entries'] = $ids->all();

            return ['deleted' => $ids->count(), 'total' => (int) Entry::withTrashed()->whereIn('id', $ids)->sum('amount_cents'), 'undo' => $undo];
        });
    }

    /** Restaura o que foi excluído (e a data final das regras). Devolve quantas compras voltaram. */
    public function undo(User $actor, array $undo): int
    {
        return DB::transaction(function () use ($actor, $undo) {
            foreach ($undo['recurrences'] ?? [] as $id => $oldEnd) {
                Recurrence::query()->where('owner_id', $actor->id)->whereKey($id)->update(['ends_on' => $oldEnd]);
            }

            return Entry::onlyTrashed()->whereIn('id', $undo['entries'] ?? [])->where('owner_id', $actor->id)->restore();
        });
    }

    /** Todas as compras do cartão no escopo (mês ou todos os meses), de qualquer dono. */
    private function scope(Card $card, ?CarbonImmutable $month): Builder
    {
        return Entry::query()->where('card_id', $card->id)->where('kind', Entry::SAIDA)
            ->when($month, fn (Builder $q) => $q->where('month', $month->startOfMonth()->toDateString()));
    }

    private function mine(User $actor, Card $card, ?CarbonImmutable $month): Builder
    {
        return $this->scope($card, $month)->where('owner_id', $actor->id);
    }
}
