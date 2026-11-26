<?php

namespace App\Services;

use App\Import\ImportPayload;
use App\Models\Card;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Person;
use App\Models\PersonDebt;
use App\Models\Recurrence;
use App\Models\User;
use App\Models\Vale;
use App\Models\ValeSpend;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Grava o que foi lido da planilha, tudo ou nada (RF-27). Mês que já tem dados é recusado.
 */
class ImportService
{
    public function monthHasData(CarbonImmutable $month): bool
    {
        $date = $month->startOfMonth()->toDateString();

        return Entry::withTrashed()->where('month', $date)->exists()
            || ValeSpend::where('month', $date)->exists()
            || PersonDebt::where('created_month', $date)->exists();
    }

    /**
     * @param  array<string, int>  $owners  dono por item (chave de ImportPayload::items()); o padrão é quem importa
     * @return array<string, int> contagens do que foi gravado
     */
    /**
     * @param  array<int, int|null>  $dueDays  "vence dia" de cada cartão da planilha (mesma ordem de $payload->cards)
     */
    public function import(User $actor, ImportPayload $payload, CarbonImmutable $month, array $owners = [], array $dueDays = []): array
    {
        Gate::forUser($actor)->authorize('create', Entry::class);
        $month = $month->startOfMonth();

        $memberIds = User::where('couple_id', $actor->couple_id)->pluck('id')->all();
        foreach ($owners as $key => $ownerId) {
            if (! in_array((int) $ownerId, $memberIds, true)) {
                throw new InvalidArgumentException('O dono de cada item precisa ser um dos dois do casal.');
            }
        }

        return DB::transaction(function () use ($actor, $payload, $month, $owners, $dueDays) {
            if ($this->monthHasData($month)) {
                throw new InvalidArgumentException('Este mês já tem dados. Limpe o mês ou escolha outro para importar.');
            }

            $ownerOf = fn (string $key) => (int) ($owners[$key] ?? $actor->id);
            $outros = Category::where('name', 'Outros')->first() ?? Category::orderBy('position')->firstOrFail();
            $counts = ['entries' => 0, 'cards' => 0, 'recurrences' => 0, 'debts' => 0, 'vale_spends' => 0];

            foreach ($payload->entries as $i => $e) {
                $this->entry($actor, $ownerOf("e{$i}"), $e['type'], $e['name'], $e['amount'], $month, $outros->id, null, $e['paid']);
                $counts['entries']++;
            }

            foreach ($payload->cards as $ci => $card) {
                $model = Card::whereRaw('lower(name) = ?', [mb_strtolower($card['name'])])->first() ?? $this->newCard($actor, $card['name']);
                $counts['cards']++;

                $day = (int) ($dueDays[$ci] ?? $card['due_day'] ?? 0);
                if ($day >= 1 && $day <= 31 && $model->due_day !== $day) {
                    $model->update(['due_day' => $day]);
                }

                foreach ($card['lines'] as $li => $line) {
                    $owner = $ownerOf("c{$ci}l{$li}");
                    $recurrence = null;

                    $endsOn = ! empty($line['ends_on']) ? CarbonImmutable::parse($line['ends_on']) : null;

                    if ($line['kind'] === 'fixo' && $endsOn !== null && $endsOn->lessThan($month)) {
                        $recurrence = null; // a última parcela já passou: entra só como lançamento deste mês
                    } elseif ($line['kind'] === 'fixo') {
                        $recurrence = $this->recurrence($actor, $owner, $line['name'], $line['amount'], $month, $outros->id, $model->id, 'monthly', null, $endsOn);
                    } elseif ($line['kind'] === 'parcelado') {
                        [$k, $n] = $line['installment'];
                        $recurrence = $this->recurrence($actor, $owner, $line['name'], $line['amount'], $month->subMonths($k - 1), $outros->id, $model->id, 'installments', $n);
                    }

                    $counts['recurrences'] += $recurrence ? 1 : 0;
                    $this->entry($actor, $owner, 'saida', $line['name'], $line['amount'], $month, $outros->id, $model->id, false, $recurrence?->id,
                        $line['kind'] === 'parcelado' ? $line['installment'][0].'/'.$line['installment'][1] : null);
                    $counts['entries']++;
                }
            }

            foreach ($payload->people as $pi => $p) {
                $person = Person::whereRaw('lower(name) = ?', [mb_strtolower($p['name'])])->first() ?? $this->newPerson($actor, $p['name']);

                foreach ($p['debts'] as $di => $debt) {
                    $model = new PersonDebt([
                        'person_id' => $person->id, 'owner_id' => $ownerOf("p{$pi}d{$di}"), 'direction' => $debt['direction'],
                        'description' => $debt['name'] ?: null, 'amount_cents' => $debt['amount'], 'created_month' => $month->toDateString(),
                    ]);
                    $model->couple_id = $actor->couple_id;
                    $model->save();
                    $counts['debts']++;
                }
            }

            if ($payload->vale !== null) {
                $vale = Vale::where('owner_id', $actor->id)->first() ?? new Vale(['owner_id' => $actor->id]);
                $vale->amount_cents = $payload->vale['amount'];
                $vale->couple_id = $actor->couple_id;
                $vale->save();

                foreach ($payload->vale['spends'] as $si => $spend) {
                    $model = new ValeSpend(['vale_id' => $vale->id, 'owner_id' => $ownerOf("v{$si}"), 'name' => $spend['name'], 'amount_cents' => $spend['amount'], 'month' => $month->toDateString()]);
                    $model->couple_id = $actor->couple_id;
                    $model->save();
                    $counts['vale_spends']++;
                }
            }

            return $counts;
        });
    }

    private function entry(User $actor, int $owner, string $kind, string $name, int $amount, CarbonImmutable $month, int $categoryId, ?int $cardId, bool $paid, ?int $recurrenceId = null, ?string $label = null): Entry
    {
        $entry = new Entry([
            'owner_id' => $owner, 'kind' => $kind, 'name' => $name, 'amount_cents' => $amount, 'month' => $month->toDateString(),
            'category_id' => $categoryId, 'card_id' => $cardId, 'paid' => $paid, 'paid_by' => $paid ? $owner : null,
            'recurrence_id' => $recurrenceId, 'installment_label' => $label,
        ]);
        $entry->couple_id = $actor->couple_id;
        $entry->save();

        return $entry;
    }

    private function recurrence(User $actor, int $owner, string $name, int $amount, CarbonImmutable $starts, int $categoryId, int $cardId, string $frequency, ?int $total = null, ?CarbonImmutable $endsOn = null): Recurrence
    {
        $recurrence = new Recurrence([
            'owner_id' => $owner, 'category_id' => $categoryId, 'card_id' => $cardId, 'kind' => 'saida', 'name' => $name,
            'amount_cents' => $amount, 'frequency' => $frequency, 'starts_on' => $starts->toDateString(), 'installments_total' => $total, 'ends_on' => $endsOn?->endOfMonth()->toDateString(),
        ]);
        $recurrence->couple_id = $actor->couple_id;
        $recurrence->save();

        return $recurrence;
    }

    private function newCard(User $actor, string $name): Card
    {
        $card = new Card(['name' => $name, 'owner_id' => $actor->id]);
        $card->couple_id = $actor->couple_id;
        $card->save();

        return $card;
    }

    private function newPerson(User $actor, string $name): Person
    {
        $person = new Person(['name' => $name]);
        $person->couple_id = $actor->couple_id;
        $person->save();

        return $person;
    }
}
