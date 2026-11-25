<?php

namespace App\Livewire\Import;

use App\Import\ImportPayload;
use App\Import\InvalidImportFile;
use App\Import\OrcMesImport;
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

    /** @var array<string, mixed>|null */
    public ?array $payload = null;

    /** @var array<string, int> */
    public array $owners = [];

    public ?string $error = null;

    public ?string $done = null;

    public function mount(): void
    {
        $this->month = CarbonImmutable::now()->format('Y-m');
        Gate::authorize('create', Entry::class);
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
            $payload = $parser->parse($this->file->getRealPath());
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
    }

    public function confirm(ImportService $service): void
    {
        $this->error = null;

        if ($this->payload === null) {
            return;
        }

        try {
            $counts = $service->import(auth()->user(), ImportPayload::fromArray($this->payload), $this->monthDate(), $this->owners);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->done = "Importado: {$counts['entries']} lançamentos, {$counts['cards']} cartões, {$counts['debts']} pendências de pessoas.";
        $this->payload = null;
        $this->file = null;
    }

    public function cancel(): void
    {
        $this->payload = null;
        $this->file = null;
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
