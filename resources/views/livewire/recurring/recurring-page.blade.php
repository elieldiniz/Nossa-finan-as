@php use App\Support\Money; $readOnly = $this->context()->isReadOnly(); @endphp
<div class="flex flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Recorrentes e assinaturas" subtitle="Entram sozinhos todo mês, ou todo ano." />

    <section class="nf-hero p-6"><p class="text-xs font-bold uppercase tracking-widest text-white/85">Total recorrente deste mês</p><p class="mt-1 text-4xl font-extrabold" data-testid="total">{{ Money::format($total) }}</p></section>

    <section class="nf-card px-5 py-3" aria-label="Lista de recorrentes">
        @forelse ($items as $i)
            @php $r = $i['rec']; @endphp
            <div class="nf-row" wire:key="rec-{{ $r->id }}" data-testid="rec-{{ $r->id }}">
                <span class="flex size-10 items-center justify-center rounded-2xl bg-brand-soft text-brand"><x-nav.icon name="repeat" class="size-5" /></span>
                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">{{ $r->name }}</span>
                    <span class="text-xs text-muted">{{ $r->card?->name ?? 'Sem cartão' }} · {{ $r->frequency === 'yearly' ? 'todo ano' : 'todo mês' }}{{ $r->ends_on ? ' · até '.$r->ends_on->format('d/m/Y') : '' }}{{ $i['active'] ? '' : ' · fora deste mês' }}</span></span>
                <span class="text-sm font-extrabold {{ $i['active'] ? '' : 'text-muted' }}">{{ Money::format($r->amount_cents) }}</span>
            </div>
        @empty
            <p class="py-4 text-sm text-muted">Nenhum recorrente ainda.</p>
        @endforelse
    </section>

    @unless ($readOnly)
        <form wire:submit="save" class="nf-card flex flex-col gap-3 p-5" aria-label="Novo recorrente">
            <h2 class="text-base font-extrabold">Nova assinatura ou fixo</h2>
            <div><label for="rn" class="nf-label">Nome</label><input id="rn" type="text" wire:model="name" placeholder="Ex.: streaming, internet" class="nf-input" />@error('name') <p class="text-sm font-bold text-neg">{{ $message }}</p> @enderror</div>
            <div class="grid grid-cols-2 gap-3">
                <div><label for="ra" class="nf-label">Valor</label><input id="ra" type="text" inputmode="decimal" wire:model="amount" placeholder="R$ 0,00" class="nf-input" /></div>
                <div><label for="rd" class="nf-label">Cobra no dia</label><input id="rd" type="number" min="1" max="31" wire:model="chargeDay" class="nf-input" /></div>
                <div><label for="rc" class="nf-label">Cartão</label><select id="rc" wire:model="cardId" class="nf-input"><option value="">Sem cartão</option>@foreach ($cards as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                <div><label for="rk" class="nf-label">Categoria</label><select id="rk" wire:model="categoryId" class="nf-input"><option value="">Escolha…</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>@error('categoryId') <p class="text-sm font-bold text-neg">{{ $message }}</p> @enderror</div>
                <div><label for="rf" class="nf-label">Repetir</label><select id="rf" wire:model="frequency" class="nf-input"><option value="monthly">Todo mês</option><option value="yearly">Todo ano</option></select></div>
                <div><label for="re" class="nf-label">Termina</label><select id="re" wire:model.live="endMode" class="nf-input"><option value="never">Nunca</option><option value="date">Em uma data</option></select></div>
            </div>
            @if ($endMode === 'date') <input type="date" wire:model="endsOn" aria-label="Data final" class="nf-input" /> @endif
            @if ($error) <p role="alert" class="text-sm font-bold text-neg" data-testid="form-error">{{ $error }}</p> @endif
            <button type="submit" class="nf-btn">Salvar recorrente</button>
        </form>
    @endunless
</div>
