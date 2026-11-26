@php use App\Support\Money; @endphp
<div class="flex flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Cartões" subtitle="Cadastre uma vez: valem para todos os meses" />

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($summary['cards'] as $c)
            <a href="{{ route('cards.show', ['card' => $c['card']->id, 'm' => $month, 'v' => $view]) }}" wire:navigate wire:key="card-{{ $c['card']->id }}" class="nf-card block p-5" aria-label="Abrir cartão {{ $c['card']->name }}" data-testid="card-{{ $c['card']->id }}">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-ink text-lime"><x-nav.icon name="card" /></span>
                    <div class="min-w-0 flex-1"><h2 class="truncate text-base font-extrabold">{{ $c['card']->name }}</h2><p class="text-xs text-muted">{{ $isFuture ? 'Fatura prevista' : 'Fatura do mês' }}@if ($c['card']->due_day) · vence dia {{ $c['card']->due_day }}@endif</p></div>
                    <span class="text-lg font-extrabold" data-testid="invoice-total">{{ Money::format($c['total']) }}</span>
                </div>

                @if ($c['card']->limit_cents)
                    @php $used = min(100, intdiv($c['total'] * 100, max($c['card']->limit_cents, 1))); @endphp
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-line"><div class="h-full bg-brand" style="width: {{ $used }}%"></div></div>
                    <p class="mt-1 text-xs text-muted">{{ $used }}% do limite de {{ Money::format($c['card']->limit_cents) }}</p>
                @endif

                <div class="mt-3 flex items-center justify-between text-sm">
                    <span class="font-semibold text-muted" data-testid="card-count">{{ $c['lines']->count() }} {{ $c['lines']->count() === 1 ? 'compra' : 'compras' }}@if ($c['paid']) · fatura paga ✓ @endif</span>
                    <span class="font-bold text-brand">Abrir ›</span>
                </div>
            </a>
        @empty
            <p class="nf-card p-5 text-sm text-muted">Nenhum cartão cadastrado ainda. Cadastre abaixo.</p>
        @endforelse
    </div>

    <form wire:submit="addCard" class="nf-card flex flex-col gap-3 p-5" aria-label="{{ $editingId ? 'Editar cartão' : 'Novo cartão' }}">
        <h2 class="text-base font-extrabold">{{ $editingId ? 'Editar cartão' : 'Novo cartão' }}</h2>
        <div><label for="card-name" class="nf-label">Nome do cartão</label><input id="card-name" type="text" wire:model="name" placeholder="Ex.: Bradesco" class="nf-input" />@error('name') <p class="text-sm font-bold text-neg">{{ $message }}</p> @enderror</div>
        <div class="grid grid-cols-3 gap-3">
            <div><label for="closing" class="nf-label">Fecha dia</label><input id="closing" type="number" min="1" max="31" wire:model="closingDay" class="nf-input" /></div>
            <div><label for="due" class="nf-label">Vence dia</label><input id="due" type="number" min="1" max="31" wire:model="dueDay" class="nf-input" /></div>
            <div><label for="limit" class="nf-label">Limite</label><input id="limit" type="text" inputmode="decimal" wire:model="limit" placeholder="R$ 0,00" class="nf-input" /></div>
        </div>
        <button type="submit" class="nf-btn">{{ $editingId ? 'Salvar cartão' : '+ Novo cartão' }}</button>
    </form>
</div>
