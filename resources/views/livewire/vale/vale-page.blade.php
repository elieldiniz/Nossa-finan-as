@php use App\Support\Money; $readOnly = $this->context()->isReadOnly(); @endphp
<div class="flex flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Vale" subtitle="Gastos pessoais. O valor se repete e a sobra não passa para o mês seguinte." />

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($cards as $c)
            <section class="nf-hero p-6" wire:key="vale-{{ $c['vale']->id }}" aria-label="Vale de {{ $c['vale']->owner?->name }}" data-testid="vale-{{ $c['vale']->owner_id }}">
                <p class="text-xs font-bold uppercase tracking-widest text-white/85">Vale de {{ $c['vale']->owner?->name }} · saldo restante</p>
                <p class="mt-1 text-4xl font-extrabold text-lime" data-testid="remaining">{{ Money::format($c['remaining']) }}</p>
                @php $pct = $c['vale']->amount_cents > 0 ? max(0, min(100, intdiv($c['remaining'] * 100, $c['vale']->amount_cents))) : 0; @endphp
                <div class="mt-3 h-3 overflow-hidden rounded-full bg-white/20"><div class="h-full rounded-full bg-lime" style="width: {{ $pct }}%"></div></div>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm"><div class="rounded-2xl bg-white/12 px-3 py-2"><div class="text-xs text-white/85">Valor do vale</div><b data-testid="amount">{{ Money::format($c['vale']->amount_cents) }}</b></div><div class="rounded-2xl bg-white/12 px-3 py-2"><div class="text-xs text-white/85">Gastos</div><b>{{ Money::format($c['spends']->sum('amount_cents')) }}</b></div></div>
                <div class="mt-3 rounded-2xl bg-white p-3 text-ink">
                    @forelse ($c['spends'] as $spend)
                        <div class="nf-row" wire:key="spend-{{ $spend->id }}"><span class="flex-1 text-sm font-bold">{{ $spend->name }}</span><span class="text-sm font-extrabold">{{ Money::format($spend->amount_cents) }}</span>
                            @if ($c['mine'] && ! $readOnly)<button type="button" wire:click="removeSpend({{ $spend->id }})" aria-label="Remover gasto {{ $spend->name }}" class="flex size-9 items-center justify-center rounded-full text-neg">✕</button>@endif</div>
                    @empty
                        <p class="py-2 text-sm text-muted">Nenhum gasto neste mês ainda.</p>
                    @endforelse
                </div>
            </section>
        @empty
            <p class="nf-card p-5 text-sm text-muted lg:col-span-2">Nenhum vale definido ainda.</p>
        @endforelse
    </div>

    @unless ($readOnly)
        <form wire:submit="saveVale" class="nf-card flex items-end gap-3 p-5" aria-label="Valor do vale">
            <div class="flex-1"><label for="vale" class="nf-label">{{ $hasOwn ? 'Alterar o valor do seu vale (vale para os próximos meses)' : 'Valor do seu vale' }}</label><input id="vale" type="text" inputmode="decimal" wire:model="valeAmount" placeholder="R$ 2.250,00" class="nf-input" /></div>
            <button type="submit" class="nf-btn">Salvar</button>
        </form>

        <form wire:submit="addSpend" class="nf-card flex flex-col gap-3 p-5" aria-label="Novo gasto do vale">
            <h2 class="text-base font-extrabold">Novo gasto do vale</h2>
            <div class="grid grid-cols-2 gap-3"><div><label for="sn" class="nf-label">Nome</label><input id="sn" type="text" wire:model="spendName" class="nf-input" /></div><div><label for="sa" class="nf-label">Valor</label><input id="sa" type="text" inputmode="decimal" wire:model="spendAmount" placeholder="R$ 0,00" class="nf-input" /></div></div>
            @if ($error) <p role="alert" class="text-sm font-bold text-neg" data-testid="form-error">{{ $error }}</p> @endif
            <button type="submit" class="nf-btn">+ Novo gasto pessoal</button>
        </form>
    @endunless
</div>
