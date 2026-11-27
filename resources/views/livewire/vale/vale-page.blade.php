@php use App\Support\Money; $readOnly = $this->context()->isReadOnly(); @endphp
<div class="flex flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Vale alimentação" subtitle="Gastos pessoais. O valor se repete e a sobra não passa para o mês seguinte." />

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($cards as $c)
            <section class="nf-hero p-6" wire:key="vale-{{ $c['vale']->id }}" aria-label="Vale alimentação de {{ $c['vale']->owner?->name }}" data-testid="vale-{{ $c['vale']->owner_id }}">
                <p class="text-xs font-bold uppercase tracking-widest text-white/85">Vale alimentação de {{ $c['vale']->owner?->name }} · saldo restante</p>
                <p class="mt-1 text-4xl font-extrabold text-lime" data-testid="remaining">{{ Money::format($c['remaining']) }}</p>
                @php $pct = $c['vale']->amount_cents > 0 ? max(0, min(100, intdiv($c['remaining'] * 100, $c['vale']->amount_cents))) : 0; @endphp
                <div class="mt-3 h-3 overflow-hidden rounded-full bg-white/20"><div class="h-full rounded-full bg-lime" style="width: {{ $pct }}%"></div></div>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm"><div class="rounded-2xl bg-white/12 px-3 py-2"><div class="text-xs text-white/85">Valor do vale</div><b data-testid="amount">{{ Money::format($c['vale']->amount_cents) }}</b></div><div class="rounded-2xl bg-white/12 px-3 py-2"><div class="text-xs text-white/85">Gastos</div><b>{{ Money::format($c['spends']->sum('amount_cents')) }}</b></div></div>
                <div class="mt-3 rounded-2xl bg-white p-3 text-ink">
                    @forelse ($c['spends'] as $spend)
                        <div class="nf-row" wire:key="spend-{{ $spend->id }}"><span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">{{ $spend->name }}</span><span class="text-xs text-muted" data-testid="spend-meta">{{ $spend->statementDate()->format('d/m') }} · {{ $spend->category?->name ?? 'Sem categoria' }}</span></span><span class="text-sm font-extrabold">{{ Money::format($spend->amount_cents) }}</span>
                            @if ($c['mine'] && ! $readOnly)<button type="button" wire:click="editSpend({{ $spend->id }})" aria-label="Editar gasto {{ $spend->name }}" data-testid="edit-spend" class="flex size-9 items-center justify-center rounded-full text-muted"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button><button type="button" wire:click="removeSpend({{ $spend->id }})" aria-label="Remover gasto {{ $spend->name }}" class="flex size-9 items-center justify-center rounded-full text-neg">✕</button>@endif</div>
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
        <form wire:submit="saveVale" class="nf-card flex items-end gap-3 p-5" aria-label="Valor do vale alimentação">
            <div class="flex-1"><label for="vale" class="nf-label">{{ $hasOwn ? 'Alterar o valor do seu vale alimentação (vale para os próximos meses)' : 'Valor do seu vale alimentação' }}</label><input id="vale" type="text" inputmode="decimal" wire:model="valeAmount" placeholder="R$ 2.250,00" class="nf-input" /></div>
            <button type="submit" class="nf-btn">Salvar</button>
        </form>

        <form wire:submit="addSpend" class="nf-card flex flex-col gap-3 p-5" aria-label="Novo gasto do vale alimentação">
            <h2 class="text-base font-extrabold" data-testid="spend-form-title">{{ $editingSpendId ? 'Editar gasto do vale alimentação' : 'Novo gasto do vale alimentação' }}</h2>
            <div class="grid grid-cols-2 gap-3"><div><label for="sn" class="nf-label">Nome</label><input id="sn" type="text" wire:model="spendName" class="nf-input" /></div><div><label for="sa" class="nf-label">Valor</label><input id="sa" type="text" inputmode="decimal" wire:model="spendAmount" placeholder="R$ 0,00" class="nf-input" /></div></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label for="scat" class="nf-label">Categoria</label><select id="scat" wire:model.live="spendCategoryId" class="nf-input" data-testid="spend-category"><option value="">Sem categoria</option>@foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</select></div>
                <div><label for="sdate" class="nf-label">Data</label><input id="sdate" type="date" wire:model="spentOn" min="{{ $this->monthDate()->startOfMonth()->toDateString() }}" max="{{ $this->monthDate()->endOfMonth()->toDateString() }}" class="nf-input" data-testid="spend-date" /></div>
            </div>
            @if ($error) <p role="alert" class="text-sm font-bold text-neg" data-testid="form-error">{{ $error }}</p> @endif
            <button type="submit" class="nf-btn" data-testid="spend-submit">{{ $editingSpendId ? 'Salvar alterações' : '+ Novo gasto pessoal' }}</button>
            @if ($editingSpendId) <button type="button" wire:click="cancelEdit" class="nf-btn-ghost" data-testid="spend-cancel">Cancelar edição</button> @endif
        </form>
    @endunless

    @unless ($readOnly)
        <section class="nf-card p-5" aria-label="Importar extrato" data-testid="import">
            <h2 class="text-base font-extrabold">Importar extrato</h2>
            <p class="mt-1 text-sm text-muted">Traga os gastos do extrato do seu cartão de vale (arquivo OFX ou PDF) para o vale alimentação.</p>
            <a href="{{ route('vale.import') }}" wire:navigate class="nf-btn mt-3 !min-h-11 !px-5 !text-sm" data-testid="import-link">Importar extrato (.ofx ou .pdf)</a>
        </section>
    @endunless

    <section class="nf-card p-5" aria-label="Exportar extrato" data-testid="export">
        <h2 class="text-base font-extrabold">Exportar extrato</h2>
        <p class="mt-1 text-sm text-muted">Baixe o extrato do vale alimentação em PDF (para ler ou imprimir) ou OFX (para outros programas).</p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <select wire:model.live="exportScope" aria-label="Período do extrato" class="nf-input !w-auto" data-testid="export-scope"><option value="mes">Só {{ $this->monthDate()->translatedFormat('F \d\e Y') }}</option><option value="tudo">Todos os meses</option></select>
            @php $params = ['m' => $exportScope === 'tudo' ? 'tudo' : $month, 'v' => $view]; @endphp
            <a href="{{ route('vale.export', ['format' => 'pdf'] + $params) }}" class="nf-btn !min-h-11 !px-5 !text-sm" data-testid="export-pdf">Baixar PDF</a>
            <a href="{{ route('vale.export', ['format' => 'ofx'] + $params) }}" class="nf-btn-ghost !min-h-11 !text-sm" data-testid="export-ofx">Baixar OFX</a>
        </div>
    </section>
</div>
