<?php

namespace App\Livewire\Import;

use App\Import\ImportPayload;
use App\Import\InvalidImportFile;
use App\Import\OrcMesImport;
use App\Models\Card;
use App\Models\Entry;
use App\Models\User;
use App\Services\ImportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
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
        $this->dueDays = [];
        foreach ($payload->cards as $ci => $card) {
            $this->dueDays[$ci] = Card::whereRaw('lower(name) = ?', [mb_strtolower($card['name'])])->value('due_day');
        }
    }

    public function confirm(ImportService $service): void
    {
        $this->error = null;

        if ($this->payload === null) {
            return;
        }

        try {
            $counts = $service->import(auth()->user(), ImportPayload::fromArray($this->payload), $this->monthDate(), $this->owners, array_map(fn ($d) => $d === '' || $d === null ? null : (int) $d, $this->dueDays));
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->done = "Importado: {$counts['entries']} lançamentos, {$counts['cards']} cartões, {$counts['debts']} pendências de pessoas.";
        $this->payload = null;
        $this->file = null;
        $this->sheets = [];
        $this->sheet = null;
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
        $payload = $this->payload ? ImportPayload::fromArray($this->payload) : null;

        return view('livewire.import.import-page', [
            'preview' => $payload,
            'items' => $payload?->items() ?? [],
            'closing' => $payload?->closing(),
            'members' => User::where('couple_id', auth()->user()->couple_id)->orderBy('id')->get(),
        ]);
    }
}
