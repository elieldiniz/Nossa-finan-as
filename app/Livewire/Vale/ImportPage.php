<?php

namespace App\Livewire\Vale;

use App\Import\InvalidImportFile;
use App\Import\OfxCategorizer;
use App\Import\ValeStatementParser;
use App\Models\Category;
use App\Models\Vale;
use App\Models\ValeSpend;
use App\Services\Vale\ValeImportService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Importar o extrato do vale alimentação (.ofx ou .pdf): confere os gastos e grava no seu vale. */
#[Layout('components.layouts.app')]
#[Title('Importar extrato do vale · Nossa Finanças')]
class ImportPage extends Component
{
    use WithFileUploads;

    public $file = null;

    /** @var array<string, mixed>|null */
    public ?array $statement = null;

    /** Uma por lançamento do extrato (include, category_id e os dados lidos). @var array<int, array<string, mixed>> */
    public array $lines = [];

    /** Valor do vale (todo mês): obrigatório para quem ainda não tem vale; sugerido pelas recargas do extrato. */
    public string $valeAmount = '';

    public bool $changeVale = false;

    public ?string $error = null;

    public ?string $done = null;

    public function mount(): void
    {
        Gate::authorize('create', ValeSpend::class);
    }

    public function updatedFile(ValeStatementParser $parser, ValeImportService $service): void
    {
        $this->error = $this->done = null;
        $this->statement = null;
        $this->lines = [];

        if (! $this->file) {
            return;
        }

        $this->validate(['file' => ['file', 'max:5120', 'extensions:ofx,pdf']], ['file.extensions' => 'O arquivo precisa ser um extrato .ofx ou .pdf.', 'file.max' => 'O arquivo é grande demais (máximo 5 MB).']);

        try {
            $isPdf = strtolower((string) $this->file->getClientOriginalExtension()) === 'pdf';
            $statement = $isPdf ? $parser->parsePdf((string) $this->file->get()) : $parser->parseOfx((string) $this->file->get());
        } catch (InvalidImportFile $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->statement = $statement;
        $vale = Vale::where('owner_id', auth()->id())->first();
        $categories = Category::forKind('saida')->get();
        $byName = $categories->keyBy(fn (Category $c) => mb_strtolower($c->name));

        // recarga do extrato = sugestão do valor do vale (a maior)
        $credits = collect($statement['transactions'])->filter(fn ($t) => $t['amount'] > 0)->pluck('amount');
        $this->valeAmount = $vale ? '' : ($credits->isNotEmpty() ? Money::format((int) $credits->max()) : '');

        foreach ($statement['transactions'] as $i => $t) {
            $isCredit = $t['amount'] > 0;
            $line = ['id' => $t['id'], 'name' => $t['name'], 'amount' => abs($t['amount']), 'date' => $t['date']];
            $duplicate = $vale && ! $isCredit && $service->alreadyImported($vale, $line);

            // categoria: a do próprio extrato se existir entre as suas; senão pelo nome do estabelecimento
            $category = ! empty($t['heading']) ? $byName->get(mb_strtolower($t['heading'])) : null;
            $category ??= $byName->get(mb_strtolower(OfxCategorizer::suggest($t['name'])));

            $this->lines[$i] = $t + [
                'credit' => $isCredit,
                'duplicate' => (bool) $duplicate,
                'include' => ! $isCredit && ! $duplicate,
                'category_id' => $category?->id,
            ];
        }
    }

    public function selectAll(bool $on): void
    {
        foreach ($this->lines as $i => $line) {
            $this->lines[$i]['include'] = $on && ! $line['credit'] && ! $line['duplicate'];
        }
    }

    public function confirm(ValeImportService $service): void
    {
        $this->error = $this->done = null;

        if ($this->statement === null) {
            return;
        }

        $chosen = collect($this->lines)->filter(fn ($l) => $l['include'] && ! $l['credit'] && ! $l['duplicate']);

        if ($chosen->isEmpty()) {
            $this->error = 'Marque ao menos um gasto para importar.';

            return;
        }

        $vale = Vale::where('owner_id', auth()->id())->first();
        $amount = null;

        if ($vale === null || $this->changeVale) {
            try {
                $amount = $this->valeAmount === '' ? null : Money::parse($this->valeAmount);
            } catch (InvalidArgumentException) {
                $this->error = 'Informe um valor válido para o vale.';

                return;
            }
        }

        try {
            $counts = $service->import(auth()->user(), $chosen->map(fn ($l) => [
                'id' => $l['id'], 'name' => $l['name'], 'amount' => abs($l['amount']), 'date' => $l['date'], 'category_id' => $l['category_id'] ? (int) $l['category_id'] : null,
            ])->values()->all(), $amount);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->error = 'Não consegui gravar a importação. Motivo: '.class_basename($e).' — '.Str::limit($e->getMessage(), 160);

            return;
        }

        $this->done = "Importado: {$counts['created']} gastos no vale alimentação"
            .($counts['months'] > 1 ? " (em {$counts['months']} meses)" : '')
            .($counts['skipped'] ? ". {$counts['skipped']} já existiam e foram pulados" : '').'.';
        $this->statement = null;
        $this->lines = [];
        $this->file = null;
        $this->changeVale = false;
    }

    public function cancel(): void
    {
        $this->statement = null;
        $this->lines = [];
        $this->file = null;
        $this->error = null;
    }

    public function render()
    {
        $included = collect($this->lines)->filter(fn ($l) => $l['include'] && ! $l['credit'] && ! $l['duplicate']);
        $firstDate = $included->min('date');

        return view('livewire.vale.import-page', [
            'hasVale' => Vale::where('owner_id', auth()->id())->exists(),
            'categories' => Category::forKind('saida')->orderBy('position')->get(),
            'count' => $included->count(),
            'total' => (int) $included->sum(fn ($l) => abs($l['amount'])),
            'credits' => (int) collect($this->lines)->filter(fn ($l) => $l['credit'])->sum('amount'),
            'monthsCount' => $included->map(fn ($l) => substr($l['date'], 0, 7))->unique()->count(),
            'firstMonth' => $firstDate ? CarbonImmutable::parse($firstDate)->translatedFormat('F \d\e Y') : null,
        ]);
    }
}
