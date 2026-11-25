@php use App\Support\Money; @endphp
<div class="flex flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Cartões" subtitle="Cadastre uma vez: valem para todos os meses" />

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($summary['cards'] as $c)
            <section class="nf-card p-5" wire:key="card-{{ $c['card']->id }}" aria-label="Cartão {{ $c['card']->name }}" data-testid="card-{{ $c['card']->id }}">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-ink text-lime"><x-nav.icon name="card" /></span>
                    <div class="flex-1"><h2 class="text-base font-extrabold">{{ $c['card']->name }}</h2><p class="text-xs text-muted">{{ $isFuture ? 'Fatura prevista' : 'Fatura do mês' }}@if ($c['card']->due_day) · vence dia {{ $c['card']->due_day }}@endif</p></div>
                    <span class="text-lg font-extrabold" data-testid="invoice-total">{{ Money::format($c['total']) }}</span>
                </div>

                @if ($c['card']->limit_cents)
                    @php $used = min(100, intdiv($c['total'] * 100, max($c['card']->limit_cents, 1))); @endphp
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-line"><div class="h-full bg-brand" style="width: {{ $used }}%"></div></div>
                    <p class="mt-1 text-xs text-muted">{{ $used }}% do limite de {{ Money::format($c['card']->limit_cents) }}</p>
                @endif

                <div class="mt-2">
                    @forelse ($c['lines'] as $line)
                        <a href="{{ route('entry.show', $line->id) }}" wire:navigate class="nf-row">
                            <span class="flex-1 text-sm font-bold">{{ $line->name }}@if ($line->installment_label) <span class="text-xs font-semibold text-muted">· parcela {{ $line->installment_label }}</span>@elseif ($line->recurrence_id) <span class="text-xs font-semibold text-muted">· todo mês</span>@endif</span>
                            <span class="text-sm font-extrabold">{{ Money::format($line->amount_cents) }}</span>
                        </a>
                    @empty
                        <p class="py-2 text-sm text-muted">Sem lançamentos neste mês.</p>
                    @endforelse
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('entry.create', ['cartao' => $c['card']->id, 'r' => 'installments', 'm' => $month]) }}" wire:navigate class="nf-chip !bg-lime !text-ink">+ Parcelado</a>
                    <a href="{{ route('entry.create', ['cartao' => $c['card']->id, 'r' => 'monthly', 'm' => $month]) }}" wire:navigate class="nf-chip">+ Fixo</a>
                    @if ($c['total'] > 0)
                        <button type="button" wire:click="toggleInvoice({{ $c['card']->id }})" class="nf-chip">{{ $c['paid'] ? 'Fatura paga ✓' : 'Marcar fatura paga' }}</button>
                    @endif
                    <button type="button" wire:click="edit({{ $c['card']->id }})" class="nf-chip">Editar</button>
                    <button type="button" wire:click="remove({{ $c['card']->id }})" wire:confirm="Excluir este cartão?" class="nf-chip !text-neg">Excluir</button>
                </div>
            </section>
        @endforeach
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
