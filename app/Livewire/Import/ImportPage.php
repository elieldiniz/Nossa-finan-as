<?php

namespace App\Livewire\Import;

use App\Import\ImportPayload;
use App\Import\InvalidImportFile;
use App\Import\OfxCategorizer;
use App\Import\OrcMesImport;
use App\Models\Card;
use App\Models\Category;
use App\Models\Entry;
use App\Models\User;
use App\Services\ImportService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Importar a planilha "Orç - Mês" (RF-27): upload, prévia com totais e troca de dono por item; só grava ao confirmar.
 */
#[Layout('components.layouts.app')]
#[Title('Importar planilha · Nossa Finanças')]
class ImportPage extends Component
{
    use WithFileUploads;

    public $file = null;

    public string $month = '';

    /** Abas "Orç - Mês…" do arquivo escolhido e a aba a importar. @var array<int, string> */
    public array $sheets = [];

    public ?string $sheet = null;

    /** @var array<string, mixed>|null */
    public ?array $payload = null;

    /** @var array<string, int> */
    public array $owners = [];

    /** Ajustes de cada item na prévia (chave: e0, c0l1, p0d2, v0): include, name, amount (texto), category_id. @var array<string, array<string, mixed>> */
    public array $edits = [];

    /** Cartão já cadastrado para cada cartão da planilha (índice => id; vazio = criar novo). @var array<int, int|string|null> */
    public array $cardMap = [];

    /** "Vence dia" de cada cartão da planilha (índice do cartão => dia). @var array<int, int|string|null> */
    public array $dueDays = [];

    /** Cópia local do arquivo enviado (não vai para o navegador). */
    private ?string $localPath = null;

    public ?string $error = null;

    public ?string $done = null;

    public function mount(): void
    {
        $this->month = CarbonImmutable::now()->format('Y-m');
        Gate::authorize('create', Entry::class);
    }

    /** Ao escolher o arquivo: lista as abas e já sugere o mês de destino. */
    public function updatedFile(OrcMesImport $parser): void
    {
        $this->error = $this->done = null;
        $this->payload = null;
        $this->sheets = [];
        $this->sheet = null;

        if (! $this->file) {
            return;
        }

        try {
            $this->sheets = $parser->sheetNames($this->localFile());
            $this->sheet = $this->sheets[0] ?? null;
            $this->suggestMonth($parser);
        } catch (InvalidImportFile $e) {
            $this->error = $e->getMessage();
        }
    }

    public function updatedSheet(OrcMesImport $parser): void
    {
        $this->payload = null;
        $this->suggestMonth($parser);
    }

    /** O mês do título da aba ("Financeiro de Outubro…") vira o mês de destino sugerido (no ano atual). */
    private function suggestMonth(OrcMesImport $parser): void
    {
        try {
            $detected = $parser->parse($this->localFile(), $this->sheet)->detectedMonth;
        } catch (InvalidImportFile) {
            return;
        }

        if ($detected) {
            $this->month = CarbonImmutable::now()->year.'-'.str_pad((string) $detected, 2, '0', STR_PAD_LEFT);
        }
    }

    public function preview(OrcMesImport $parser, ImportService $service): void
    {
        $this->error = $this->done = null;
        $this->payload = null;

        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:2048'],
            'month' => ['required', 'date_format:Y-m'],
        ], [
            'file.required' => 'Escolha um arquivo .xlsx.',
            'file.mimes' => 'O arquivo precisa ser uma planilha .xlsx.',
            'file.max' => 'O arquivo é grande demais (máximo 2 MB).',
            'month.date_format' => 'Escolha o mês de destino.',
        ]);

        try {
            $payload = $parser->parse($this->localFile(), $this->sheet);
        } catch (InvalidImportFile $e) {
            $this->error = $e->getMessage();

            return;
        }

        if ($service->monthHasData($this->monthDate())) {
            $this->error = 'Este mês já tem dados. Limpe o mês ou escolha outro para importar.';

            return;
        }

        $this->payload = $payload->toArray();
        $this->owners = array_fill_keys(array_keys($payload->items()), auth()->id());
        $this->buildEdits($payload);
        $this->dueDays = [];
        $this->cardMap = [];
        $existing = Card::orderBy('name')->get();
        foreach ($payload->cards as $ci => $card) {
            $match = $this->matchCard($existing, $card['name']);
            $this->cardMap[$ci] = $match?->id;
            // o vencimento escrito na planilha ("Sicoob - vence dia 3") vale; senão o que o cartão já tem
            $this->dueDays[$ci] = $card['due_day'] ?? $match?->due_day;
        }
    }

    /** Um ajuste por item: entra (sim), nome, valor e categoria sugerida pelo nome (a pessoa troca se quiser). */
    private function buildEdits(ImportPayload $payload): void
    {
        $saida = Category::forKind('saida')->get()->keyBy('name');
        $entrada = Category::forKind('entrada')->get()->keyBy('name');
        $outros = Category::where('name', 'Outros')->value('id');
        $this->edits = [];

        foreach ($payload->items() as $key => $item) {
            $category = match ($item['type']) {
                'entrada' => ($entrada[str_contains(mb_strtolower($item['name']), 'sal') ? 'Salário' : 'Outros'] ?? $entrada->first())?->id ?? $outros,
                'saida', 'cartao' => ($saida[OfxCategorizer::suggest($item['name'])] ?? null)?->id ?? $outros,
                default => null,
            };

            $this->edits[$key] = ['include' => true, 'name' => $item['name'], 'amount' => Money::format($item['amount']), 'category_id' => $category];
        }
    }

    /** O que será gravado: a leitura da planilha com os ajustes da prévia aplicados (itens desmarcados ficam de fora). */
    private function effective(): ?ImportPayload
    {
        if ($this->payload === null) {
            return null;
        }

        $d = $this->payload;
        $apply = function (array $row, string $key) {
            $edit = $this->edits[$key] ?? null;

            if ($edit === null) {
                return $row;
            }

            $row['skip'] = ! ($edit['include'] ?? true);
            $name = trim((string) ($edit['name'] ?? ''));
            $row['name'] = $name !== '' ? $name : $row['name'];

            try {
                $amount = Money::parse((string) ($edit['amount'] ?? ''));
                $row['amount'] = $amount > 0 ? $amount : $row['amount'];
                $row['amount_invalid'] = $amount <= 0;
            } catch (InvalidArgumentException) {
                $row['amount_invalid'] = true;
            }

            if (! empty($edit['category_id'])) {
                $row['category_id'] = (int) $edit['category_id'];
            }

            return $row;
        };

        foreach ($d['entries'] as $i => $e) {
            $d['entries'][$i] = $apply($e, "e{$i}");
        }
        foreach ($d['cards'] as $ci => $card) {
            foreach ($card['lines'] as $li => $line) {
                $d['cards'][$ci]['lines'][$li] = $apply($line, "c{$ci}l{$li}");
            }
        }
        foreach ($d['people'] as $pi => $person) {
            foreach ($person['debts'] as $di => $debt) {
                $d['people'][$pi]['debts'][$di] = $apply($debt, "p{$pi}d{$di}");
            }
        }
        foreach ($d['vale']['spends'] ?? [] as $si => $spend) {
            $d['vale']['spends'][$si] = $apply($spend, "v{$si}");
        }

        return ImportPayload::fromArray($d);
    }

    private function amountInvalid(string $key): bool
    {
        try {
            return Money::parse((string) ($this->edits[$key]['amount'] ?? '')) <= 0;
        } catch (InvalidArgumentException) {
            return true;
        }
    }

    public function selectAll(bool $on): void
    {
        foreach ($this->edits as $key => $edit) {
            $this->edits[$key]['include'] = $on;
        }
    }

    /** Ao trocar o cartão de uma linha, traz o vencimento dele (se a planilha não informou). */
    public function updatedCardMap($value, $key): void
    {
        $key = (int) explode('.', (string) $key)[0];
        $card = $value ? Card::find((int) $value) : null;
        $fromSheet = $this->payload['cards'][$key]['due_day'] ?? null;

        $this->dueDays[$key] = $fromSheet ?? $card?->due_day;
    }

    /** Cartão já cadastrado que combina com o nome da planilha: nome igual; senão um que contém (ou está contido no) nome. */
    private function matchCard($cards, string $name): ?Card
    {
        $norm = fn (string $t) => mb_strtolower(trim(Str::ascii($t)));
        $target = $norm($name);

        return $cards->first(fn (Card $c) => $norm($c->name) === $target)
            ?? $cards->first(fn (Card $c) => $target !== '' && (str_contains($norm($c->name), $target) || str_contains($target, $norm($c->name))));
    }

    public function confirm(ImportService $service): void
    {
        $this->error = null;

        if ($this->payload === null) {
            return;
        }

        $effective = $this->effective();

        foreach ($effective->items() as $key => $item) {
            if (! $item['skip'] && $this->amountInvalid($key)) {
                $this->error = "Valor inválido em “{$item['name']}”. Informe um valor maior que zero.";

                return;
            }
        }

        if (collect($effective->items())->every(fn ($i) => $i['skip'])) {
            $this->error = 'Marque ao menos um item para importar.';

            return;
        }

        try {
            $counts = $service->import(auth()->user(), $effective, $this->monthDate(), $this->owners, array_map(fn ($d) => $d === '' || $d === null ? null : (int) $d, $this->dueDays), $this->cardMap);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->done = "Importado: {$counts['entries']} lançamentos, {$counts['cards']} cartões, {$counts['debts']} pendências de pessoas.";
        $this->payload = null;
        $this->file = null;
        $this->sheets = [];
        $this->sheet = null;
        $this->cardMap = [];
    }

    public function cancel(): void
    {
        $this->payload = null;
        $this->file = null;
        $this->sheets = [];
        $this->sheet = null;
    }

    /**
     * Caminho local do arquivo enviado. Na hospedagem o envio pode ir para um armazenamento na nuvem (sem caminho local),
     * então o conteúdo é lido do arquivo enviado e gravado num arquivo temporário do servidor.
     */
    private function localFile(): string
    {
        if ($this->localPath === null || ! is_file($this->localPath)) {
            $this->localPath = tempnam(sys_get_temp_dir(), 'orc').'.xlsx';
            file_put_contents($this->localPath, $this->file->get());
        }

        return $this->localPath;
    }

    private function monthDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->month) ?: CarbonImmutable::now()->startOfMonth();
    }

    public function render()
    {
        $payload = $this->effective();

        return view('livewire.import.import-page', [
            'preview' => $payload,
            'items' => $payload?->items() ?? [],
            'closing' => $payload?->closing(),
            'members' => User::where('couple_id', auth()->user()->couple_id)->orderBy('id')->get(),
            'cards' => Card::orderBy('name')->get(),
            'catSaida' => Category::forKind('saida')->orderBy('position')->get(),
            'catEntrada' => Category::forKind('entrada')->orderBy('position')->get(),
        ]);
    }
}
