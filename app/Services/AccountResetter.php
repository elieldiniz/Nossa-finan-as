<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Entry;
use App\Models\Person;
use App\Models\PersonDebt;
use App\Models\Recurrence;
use App\Models\User;
use App\Models\Vale;
use App\Models\ValeSpend;
use Illuminate\Support\Facades\DB;

/**
 * "Apagar tudo e recomeçar": remove DEFINITIVAMENTE tudo o que a própria pessoa cadastrou (lançamentos, contas
 * recorrentes e parceladas, cartões, pendências com pessoas, vale e avisos). Não toca no que o parceiro cadastrou,
 * nem na conta, no casal, nas categorias e nas metas.
 */
class AccountResetter
{
    /** @return array{entries: int, recurrences: int, cards: int, debts: int, vale_spends: int, notifications: int} */
    public function preview(User $actor): array
    {
        return [
            'entries' => Entry::withTrashed()->where('owner_id', $actor->id)->count(),
            'recurrences' => Recurrence::where('owner_id', $actor->id)->count(),
            'cards' => Card::where('owner_id', $actor->id)->whereNotIn('id', Entry::where('owner_id', '!=', $actor->id)->whereNotNull('card_id')->pluck('card_id')->merge(Recurrence::where('owner_id', '!=', $actor->id)->whereNotNull('card_id')->pluck('card_id'))->unique())->count(),
            'debts' => PersonDebt::where('owner_id', $actor->id)->count(),
            'vale_spends' => ValeSpend::where('owner_id', $actor->id)->count(),
            'notifications' => $actor->notifications()->count(),
        ];
    }

    /** @return array{entries: int, recurrences: int, cards: int, debts: int, vale_spends: int, notifications: int} o que foi apagado */
    public function reset(User $actor): array
    {
        return DB::transaction(function () use ($actor) {
            $counts = $this->preview($actor);

            // lançamentos de verdade (inclusive os já excluídos antes): o mês fica realmente limpo para importar de novo
            Entry::withTrashed()->where('owner_id', $actor->id)->forceDelete();
            Recurrence::where('owner_id', $actor->id)->delete();

            $personIds = PersonDebt::where('owner_id', $actor->id)->pluck('person_id')->unique();
            PersonDebt::where('owner_id', $actor->id)->delete();
            // pessoas que só existiam por causa das minhas pendências saem junto
            Person::whereIn('id', $personIds)->whereDoesntHave('debts')->delete();

            ValeSpend::where('owner_id', $actor->id)->delete();
            Vale::where('owner_id', $actor->id)->delete();

            // cartões seus que o parceiro também usa ficam (apagá-los tiraria o cartão das compras dele)
            $keep = Entry::where('owner_id', '!=', $actor->id)->whereNotNull('card_id')->pluck('card_id')->merge(Recurrence::where('owner_id', '!=', $actor->id)->whereNotNull('card_id')->pluck('card_id'))->unique();
            Card::where('owner_id', $actor->id)->whereNotIn('id', $keep)->delete();
            $actor->notifications()->delete();

            return $counts;
        });
    }
}
