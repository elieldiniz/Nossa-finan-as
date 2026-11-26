@php use App\Support\Money; $readOnly = $summary['view']->isReadOnly(); @endphp
<div class="flex flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Extrato" />

    <section class="nf-card grid grid-cols-3 gap-3 p-4 text-center" aria-label="Resumo do mês">
        <div><div class="text-xs font-semibold text-muted">Entradas</div><div class="text-sm font-extrabold text-pos">{{ Money::signed($summary['closing']->income) }}</div></div>
        <div><div class="text-xs font-semibold text-muted">Saídas</div><div class="text-sm font-extrabold">{{ $summary['closing']->expense > 0 ? '−' : '' }}{{ Money::format($summary['closing']->expense) }}</div></div>
        <div><div class="text-xs font-semibold text-muted">Saldo</div><div class="text-sm font-extrabold {{ $summary['closing']->closing < 0 ? 'text-neg' : 'text-pos' }}" data-testid="closing-value">{{ Money::signed($summary['closing']->closing) }}</div></div>
    </section>

    <div class="flex flex-wrap items-center gap-2">
        @foreach (['all' => 'Todos', 'pending' => 'Pendentes', 'paid' => 'Pagos e recebidos'] as $key => $label)
            <button type="button" wire:click="setFilter('{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}" class="nf-chip {{ $filter === $key ? 'nf-chip-on' : '' }}">{{ $label }}</button>
        @endforeach
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome" aria-label="Buscar" class="nf-input !min-h-10 min-w-40 flex-1" />
    </div>

    @if ($notice)
        <div role="status" class="flex items-start justify-between gap-3 rounded-2xl bg-brand-soft px-4 py-3 text-sm font-semibold text-brand" data-testid="notice">
            <span>{{ $notice }}</span>
            <button type="button" wire:click="dismissNotice" aria-label="Fechar aviso" class="shrink-0 text-base font-extrabold">✕</button>
        </div>
    @endif

    <section class="nf-card px-4 py-2" aria-label="Lançamentos" data-testid="rows">
        @forelse ($rows as $row)
            <div class="nf-row" wire:key="row-{{ $row['source'] }}-{{ $row['id'] }}">
                @if ($row['source'] === 'person')
                    <span class="size-7 shrink-0"></span>
                @else
                    @php $mine = $row['source'] === 'card' || (int) $row['owner_id'] === auth()->id(); @endphp
                    <button type="button" @if($mine && ! $readOnly) wire:click="{{ $row['source'] === 'card' ? "toggleInvoice({$row['id']})" : "togglePaid({$row['id']})" }}" @else disabled @endif
                            role="checkbox" aria-checked="{{ $row['paid'] ? 'true' : 'false' }}" aria-label="{{ ($row['kind'] ?? '') === 'aporte' ? 'Marcar como aplicado' : ($row['signed'] >= 0 ? 'Marcar como recebido' : 'Marcar como pago') }}: {{ $row['name'] }}"
                            class="flex size-7 shrink-0 items-center justify-center rounded-full border-2 {{ $row['paid'] ? 'border-brand bg-brand text-white' : 'border-zinc-300' }} disabled:opacity-60">
                        @if ($row['paid']) <span aria-hidden="true">✓</span> @endif
                    </button>
                @endif
                <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl {{ ($row['kind'] ?? '') === 'aporte' ? 'bg-brand-soft text-brand' : ($row['signed'] >= 0 ? 'bg-pos-soft text-pos' : 'bg-neg-soft text-neg') }}"><x-nav.icon :name="$row['source'] === 'card' ? 'card' : ($row['source'] === 'person' ? 'people' : (($row['kind'] ?? '') === 'aporte' ? 'chart' : 'list'))" class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    @if ($row['source'] === 'entry')
                        <a href="{{ route('entry.show', $row['id']) }}" wire:navigate class="block truncate text-sm font-bold">{{ $row['name'] }}</a>
                    @else
                        <span class="block truncate text-sm font-bold">{{ $row['name'] }}</span>
                    @endif
                    <span class="text-xs text-muted">{{ $row['sub'] }}</span>
                </span>
                <span class="text-right">
                    <span class="block text-sm font-extrabold {{ $row['signed'] >= 0 ? 'text-pos' : '' }}">{{ Money::signed($row['signed']) }}</span>
                    @if ($row['source'] !== 'person')
                        @php $kindRow = ($row['kind'] ?? '') === 'aporte' ? ['Aplicado', 'A aplicar'] : ($row['signed'] >= 0 ? ['Recebido', 'A receber'] : ['Pago', 'A pagar']); @endphp
                        <span class="text-xs font-bold {{ $row['paid'] ? 'text-brand' : 'text-[#8A5A00]' }}" data-testid="status">{{ $row['paid'] ? '✓ '.$kindRow[0] : $kindRow[1] }}</span>
                    @endif
                </span>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-muted">Nenhum lançamento por aqui.</p>
        @endforelse
    </section>

    @unless ($readOnly)
        <form wire:submit="quickAdd" class="nf-card flex items-center gap-2 p-2 pl-5" aria-label="Lançar rápido">
            <input type="text" wire:model="quick" placeholder="Lançar rápido: 50 mercado" aria-label="Lançar rápido" class="min-w-0 flex-1 bg-transparent text-base outline-none" />
            <a href="{{ route('entry.create', ['m' => $month]) }}" wire:navigate aria-label="Mais detalhes" class="flex size-11 items-center justify-center rounded-full bg-ground font-extrabold">⋯</a>
            <button type="submit" aria-label="Adicionar" class="flex size-11 items-center justify-center rounded-full bg-brand text-white"><x-nav.icon name="plus" /></button>
        </form>
        @if ($quickError) <p role="alert" class="text-sm font-bold text-neg" data-testid="quick-error">{{ $quickError }}</p> @endif
    @endunless
</div>
