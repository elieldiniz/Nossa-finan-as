<?php

namespace App\Livewire\Import;

use App\Import\InvalidImportFile;
use App\Import\OfxCategorizer;
use App\Import\OfxParser;
use App\Models\Card;
use App\Models\Category;
use App\Models\Entry;
use App\Models\User;
use App\Services\OfxImportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Importar a fatura do cartão (arquivo .ofx do banco): confere as compras e grava no cartão escolhido. */
#[Layout('components.layouts.app')]
#[Title('Importar extrato · Nossa Finanças')]
class OfxPage extends Component
{
    use WithFileUploads;

    public $file = null;

    public ?int $cardId = null;

    public string $newCardName = '';

    /** "Vence dia" do cartão: com ele, a fatura aparece em Contas a vencer. */
    public ?int $dueDay = null;

    public string $month = '';

    public ?int $ownerId = null;

    /** @var array<string, mixed>|null */
    public ?array $statement = null;

    /** Uma por transação: include, category_id (e os dados lidos do arquivo). @var array<int, array<string, mixed>> */
    public array $lines = [];

    public ?string $error = null;

    public ?string $done = null;

    public function mount(): void
    {
        Gate::authorize('create', Entry::class);
        $this->ownerId = auth()->id();
        $this->month = CarbonImmutable::now()->format('Y-m');
    }

    public function updatedFile(OfxParser $parser, OfxImportService $service): void
    {
        $this->error = $this->done = null;
        $this->statement = null;
        $this->lines = [];

        if (! $this->file) {
            return;
        }

        $this->validate(['file' => ['file', 'max:2048', 'extensions:ofx']], ['file.extensions' => 'O arquivo precisa ser um extrato .ofx.', 'file.max' => 'O arquivo é grande demais (máximo 2 MB).']);

        try {
            $statement = $parser->parse((string) $this->file->get());
        } catch (InvalidImportFile $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->statement = $statement;

        // mês da fatura = mês em que o período termina (a fatura de novembro fecha em 30/11 e vence em dezembro)
        if ($statement['end']) {
            $this->month = CarbonImmutable::parse($statement['end'])->format('Y-m');
        }

        $this->guessCard($statement['bank']);

        $categories = Category::forKind('saida')->get()->keyBy('name');
        $outros = Category::where('name', 'Outros')->value('id') ?? $categories->first()?->id;
        $card = $this->cardId ? Card::find($this->cardId) : null;

        foreach ($statement['transactions'] as $i => $t) {
            $isCredit = $t['amount'] > 0;
            $duplicate = $card && ! $isCredit && $service->alreadyImported($card, $this->monthDate(), ['id' => $t['id'], 'name' => $t['name'], 'amount' => abs($t['amount']), 'installment' => $t['installment']]);

            $this->lines[$i] = $t + [
                'credit' => $isCredit,
                'duplicate' => $duplicate,
                'include' => ! $isCredit && ! $duplicate,
                'category_id' => $categories[OfxCategorizer::suggest($t['name'])]->id ?? $outros,
            ];
        }
    }

    /** "NU PAGAMENTOS" → o cartão "Nubank" se existir; senão sugere criar um com o nome do banco. */
    private function guessCard(?string $bank): void
    {
        $this->cardId = null;
        $this->newCardName = '';
        $bank = mb_strtolower((string) $bank);
        $hint = str_contains($bank, 'nu pagamentos') || str_contains($bank, 'nubank') ? 'nubank' : trim(explode(' ', $bank)[0] ?? '');

        $cards = Card::orderBy('name')->get();
        // nome igual ao do banco primeiro ("Nubank"); senão o primeiro que contém ("Nubank Ana")
        $match = $hint !== '' ? ($cards->first(fn (Card $c) => mb_strtolower(trim($c->name)) === $hint) ?? $cards->first(fn (Card $c) => str_contains(mb_strtolower($c->name), $hint))) : null;

        if ($match) {
            $this->cardId = $match->id;
            $this->dueDay = $match->due_day;
        } else {
            $this->dueDay = null;
            $this->newCardName = $hint === 'nubank' ? 'Nubank' : ucfirst($hint);
        }
    }

    public function updatedMonth(OfxImportService $service): void
    {
        $this->refreshDuplicates($service);
    }

    public function updatedCardId(OfxImportService $service): void
    {
        $this->dueDay = $this->cardId ? Card::find($this->cardId)?->due_day : null;
        $this->refreshDuplicates($service);
    }

    private function refreshDuplicates(OfxImportService $service): void
    {
        $card = $this->cardId ? Card::find($this->cardId) : null;

        foreach ($this->lines as $i => $line) {
            if ($line['credit']) {
                continue;
            }

            $dup = $card && $service->alreadyImported($card, $this->monthDate(), ['id' => $line['id'], 'name' => $line['name'], 'amount' => abs($line['amount']), 'installment' => $line['installment']]);
            $this->lines[$i]['duplicate'] = $dup;
            $this->lines[$i]['include'] = $dup ? false : $line['include'];
        }
    }

    public function selectAll(bool $on): void
    {
        foreach ($this->lines as $i => $line) {
            $this->lines[$i]['include'] = $on && ! $line['credit'] && ! $line['duplicate'];
        }
    }

    public function confirm(OfxImportService $service): void
    {
        $this->error = $this->done = null;

        if ($this->statement === null) {
            return;
        }

        $this->validate(['month' => ['required', 'date_format:Y-m'], 'ownerId' => ['required', 'integer'], 'dueDay' => ['nullable', 'integer', 'between:1,31']], ['month.date_format' => 'Escolha o mês da fatura.', 'dueDay.between' => 'O dia de vencimento vai de 1 a 31.']);

        $chosen = collect($this->lines)->filter(fn ($l) => $l['include'] && ! $l['credit'] && ! $l['duplicate']);

        if ($chosen->isEmpty()) {
            $this->error = 'Marque ao menos uma compra para importar.';

            return;
        }

        try {
            $card = $this->cardId ? Card::findOrFail($this->cardId) : $this->createCard();

            if ($this->dueDay && $card->due_day !== $this->dueDay) {
                Gate::authorize('update', $card);
                $card->update(['due_day' => $this->dueDay]);
            }

            $counts = $service->import(auth()->user(), $card, $this->monthDate(), $this->ownerId, $chosen->map(fn ($l) => [
                'id' => $l['id'], 'name' => $l['name'], 'amount' => abs($l['amount']), 'installment' => $l['installment'], 'category_id' => (int) $l['category_id'], 'date' => $l['date'],
            ])->values()->all());
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->done = "Importado: {$counts['created']} compras em {$card->name}"
            .($counts['skipped'] ? " ({$counts['skipped']} já existiam e foram puladas)" : '')
            .($counts['recurrences'] ? ", {$counts['recurrences']} parceladas que continuam nos próximos meses" : '').'.';
        $this->statement = null;
        $this->lines = [];
        $this->file = null;
    }

    private function createCard(): Card
    {
        $name = trim($this->newCardName);

        if ($name === '') {
            throw new InvalidArgumentException('Escolha o cartão ou digite o nome de um novo.');
        }

        $card = new Card(['name' => $name, 'owner_id' => auth()->id()]);
        $card->couple_id = auth()->user()->couple_id;
        $card->save();

        return $card;
    }

    public function cancel(): void
    {
        $this->statement = null;
        $this->lines = [];
        $this->file = null;
        $this->error = null;
    }

    private function monthDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->month) ?: CarbonImmutable::now()->startOfMonth();
    }

    public function render()
    {
        $included = collect($this->lines)->filter(fn ($l) => $l['include'] && ! $l['credit'] && ! $l['duplicate']);

        return view('livewire.import.ofx-page', [
            'cards' => Card::orderBy('name')->get(),
            'categories' => Category::forKind('saida')->orderBy('position')->get(),
            'members' => User::where('couple_id', auth()->user()->couple_id)->orderBy('id')->get(),
            'count' => $included->count(),
            'total' => (int) $included->sum(fn ($l) => abs($l['amount'])),
            'payments' => (int) collect($this->lines)->filter(fn ($l) => $l['credit'])->sum('amount'),
        ]);
    }
}
