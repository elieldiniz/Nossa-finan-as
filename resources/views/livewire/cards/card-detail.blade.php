@php use App\Support\Money; @endphp
<div class="mx-auto flex max-w-3xl flex-col gap-4">
    <header class="flex items-center gap-3">
        <a href="{{ route('cards', ['m' => $month]) }}" wire:navigate aria-label="Voltar para Cartões" class="flex size-11 items-center justify-center rounded-full bg-white text-xl">‹</a>
        <h1 class="text-xl font-extrabold">{{ $card->name }}</h1>
    </header>

    <x-month-header :month="$this->monthDate()" :view="$view" />

    <section class="nf-hero p-6" aria-label="Fatura" data-testid="card-hero">
        <p class="text-xs font-bold uppercase tracking-widest text-white/85">{{ $isFuture ? 'Fatura prevista' : 'Fatura do mês' }}@if ($card->due_day) · vence dia {{ $card->due_day }}@endif</p>
        <p class="mt-1 text-4xl font-extrabold" data-testid="invoice-total">{{ Money::format($total) }}</p>
        <p class="mt-1 text-sm font-semibold text-white/85">{{ $lines->count() }} {{ $lines->count() === 1 ? 'compra' : 'compras' }}@if ($paid) · fatura paga ✓ @endif</p>
        @if ($card->limit_cents)
            @php $used = min(100, intdiv($total * 100, max($card->limit_cents, 1))); @endphp
            <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-white/25"><div class="h-full bg-lime" style="width: {{ $used }}%"></div></div>
            <p class="mt-1 text-xs text-white/85">{{ $used }}% do limite de {{ Money::format($card->limit_cents) }}</p>
        @endif
    </section>

    @if ($cleanDone)
        <div role="status" class="flex items-center justify-between gap-3 rounded-2xl bg-brand-soft px-4 py-3 text-sm font-semibold text-brand" data-testid="clean-done">
            <span>{{ $cleanDone }}</span>
            <span class="flex shrink-0 gap-2">
                @if ($undo) <button type="button" wire:click="undoClean" class="nf-chip !min-h-9 !px-3 !text-xs" data-testid="clean-undo">Desfazer</button> @endif
                <button type="button" wire:click="dismissClean" aria-label="Fechar aviso" class="text-base font-extrabold">✕</button>
            </span>
        </div>
    @endif

    @if ($error) <p role="alert" class="rounded-2xl bg-neg-soft px-4 py-3 text-sm font-bold text-[#8E2F20]" data-testid="form-error">{{ $error }}</p> @endif

    @if ($allCount > 8)
        <input type="search" wire:model.live.debounce.250ms="search" placeholder="Buscar compra por nome ou categoria" aria-label="Buscar compra" class="nf-input" data-testid="card-search" />
    @endif

    <section class="nf-card p-3" aria-label="Compras do cartão" data-testid="card-lines">
        @forelse ($lines as $line)
            @php $mineLine = (int) $line->owner_id === auth()->id() && $view !== 'par'; @endphp
            <div class="nf-row" wire:key="line-{{ $line->id }}" data-testid="card-line">
                <span class="min-w-0 flex-1">
                    <a href="{{ route('entry.show', $line->id) }}" wire:navigate class="block truncate text-sm font-bold">{{ $line->name }}</a>
                    <span class="text-xs text-muted">@if ($line->purchased_on) <span data-testid="line-date">{{ $line->purchased_on->format('d/m') }}</span> · @endif{{ $line->category?->name ?? 'Sem categoria' }}@if ($line->installment_label) · parcela {{ $line->installment_label }}@elseif ($line->recurrence_id) · todo mês @endif</span>
                </span>
                <span class="text-sm font-extrabold">{{ Money::format($line->amount_cents) }}</span>
                @if ($mineLine)
                    <a href="{{ route('entry.show', $line->id) }}" wire:navigate aria-label="Editar {{ $line->name }}" data-testid="line-edit" class="flex size-9 shrink-0 items-center justify-center rounded-full text-muted">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </a>
                    @if ($line->recurrence_id)
                        <a href="{{ route('entry.show', $line->id) }}" wire:navigate aria-label="Excluir {{ $line->name }}…" title="Parcelada ou todo mês: escolha o que excluir na tela de edição" data-testid="line-delete-scope" class="flex size-9 shrink-0 items-center justify-center rounded-full text-neg">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 14h10l1-14"/></svg>
                        </a>
                    @else
                        <button type="button" wire:click="deleteLine({{ $line->id }})" wire:confirm="Excluir “{{ $line->name }}”?" aria-label="Excluir {{ $line->name }}" data-testid="line-delete" class="flex size-9 shrink-0 items-center justify-center rounded-full text-neg">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 14h10l1-14"/></svg>
                        </button>
                    @endif
                @endif
            </div>
        @empty
            <p class="px-2 py-6 text-center text-sm text-muted" data-testid="no-lines">@if (trim($search) !== '') Nenhuma compra com “{{ $search }}”. @else Sem compras neste mês. Use “+ Compra parcelada” ou “+ Compra fixa” abaixo, ou <a href="{{ route('import.ofx') }}" wire:navigate class="font-bold text-brand">importe a fatura (.ofx)</a>. @endif</p>
        @endforelse
    </section>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('entry.create', ['cartao' => $card->id, 'r' => 'installments', 'm' => $month]) }}" wire:navigate class="nf-chip !bg-lime !text-ink">+ Parcelado</a>
        <a href="{{ route('entry.create', ['cartao' => $card->id, 'r' => 'monthly', 'm' => $month]) }}" wire:navigate class="nf-chip">+ Fixo</a>
        @if ($total > 0 && $view !== 'par')
            <button type="button" wire:click="toggleInvoice" class="nf-chip" data-testid="toggle-invoice">{{ $paid ? 'Fatura paga ✓' : 'Marcar fatura paga' }}</button>
        @endif
        <a href="{{ route('cards', ['editar' => $card->id]) }}" wire:navigate class="nf-chip" data-testid="edit-card">Editar cartão</a>
        @if ($view !== 'par')
            <button type="button" wire:click="openClean" class="nf-chip !text-neg" data-testid="open-clean">Excluir compras…</button>
        @endif
        <button type="button" wire:click="removeCard" wire:confirm="Excluir este cartão?@if ($cardPurchases > 0) As {{ $cardPurchases }} compras dele ficam como lançamentos sem cartão (para apagá-las também, use “Excluir compras…” antes).@endif" class="nf-chip !text-neg" data-testid="remove-card">Excluir cartão</button>
    </div>

    @if ($showClean && $clean)
        <div class="fixed inset-0 z-[70] flex items-end justify-center bg-black/40 p-3 sm:items-center" role="dialog" aria-modal="true" aria-label="Excluir compras do cartão" data-testid="clean-dialog" wire:key="clean-dialog">
            <div class="w-full max-w-md rounded-3xl bg-white p-5 shadow-xl">
                <h2 class="text-lg font-extrabold">Excluir compras do {{ $card->name }}</h2>
                <fieldset class="mt-3 flex flex-col gap-1">
                    <label class="flex min-h-11 items-center gap-2 rounded-2xl border-2 px-3 text-sm font-bold {{ $cleanScope === 'month' ? 'border-brand bg-brand-soft' : 'border-line' }}"><input type="radio" wire:model.live="cleanScope" value="month" class="size-4 accent-brand" /> Só de {{ $this->monthDate()->translatedFormat('F \d\e Y') }}</label>
                    <label class="flex min-h-11 items-center gap-2 rounded-2xl border-2 px-3 text-sm font-bold {{ $cleanScope === 'all' ? 'border-brand bg-brand-soft' : 'border-line' }}"><input type="radio" wire:model.live="cleanScope" value="all" class="size-4 accent-brand" /> De todos os meses</label>
                </fieldset>

                <div class="mt-3 rounded-2xl bg-ground p-3 text-sm" data-testid="clean-summary">
                    <p class="font-extrabold"><span data-testid="clean-count">{{ $clean['count'] }}</span> {{ $clean['count'] === 1 ? 'compra sua' : 'compras suas' }} · {{ Money::format($clean['total']) }}</p>
                    @if ($clean['series'] > 0) <p class="mt-1 text-muted">{{ $clean['series'] }} {{ $clean['series'] === 1 ? 'é parcelada ou todo mês' : 'são parceladas ou todo mês' }}.</p> @endif
                    @if ($clean['others'] > 0) <p class="mt-1 font-semibold text-[#6B4600]" data-testid="clean-others">{{ $clean['others'] }} {{ $clean['others'] === 1 ? 'compra' : 'compras' }} do parceiro não {{ $clean['others'] === 1 ? 'será apagada' : 'serão apagadas' }}: cada um exclui as suas.</p> @endif
                </div>

                @if ($clean['series'] > 0)
                    <label class="mt-3 flex min-h-11 items-start gap-2 text-sm font-semibold"><input type="checkbox" wire:model="stopSeries" class="mt-0.5 size-5 shrink-0 accent-brand" data-testid="stop-series" /> <span>Parar também as parceladas e fixas daqui para frente <span class="block text-xs font-normal text-muted">Sem isso, a próxima parcela volta no mês seguinte.</span></span></label>
                @endif

                <p class="mt-3 text-xs text-muted">Você poderá desfazer logo depois. Outros cartões, vale, pessoas e contas sem cartão não são mexidos.</p>

                <div class="mt-4 flex gap-3">
                    <button type="button" wire:click="closeClean" class="nf-btn-ghost flex-1">Cancelar</button>
                    <button type="button" wire:click="cleanAll" wire:loading.attr="disabled" @disabled($clean['count'] === 0) class="nf-btn flex-1 !bg-neg" data-testid="clean-confirm">Excluir {{ $clean['count'] }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
