@php use App\Support\Money; @endphp
<div class="mx-auto flex max-w-xl flex-col gap-4">
    <header class="flex items-center gap-3">
        <a href="{{ route('statement', ['m' => $entry->month->format('Y-m')]) }}" wire:navigate aria-label="Voltar" class="flex size-11 items-center justify-center rounded-full bg-white text-xl">‹</a>
        <h1 class="text-xl font-extrabold">Lançamento</h1>
    </header>

    <section class="nf-hero p-6 text-center">
        <p class="text-sm font-bold text-white/85">{{ $entry->name }}@if ($entry->installment_label) · parcela {{ $entry->installment_label }}@endif</p>
        <p class="mt-1 text-4xl font-extrabold">{{ $entry->kind === 'entrada' ? '+' : '−' }}{{ Money::format($entry->amount_cents) }}</p>
        <p class="mt-2 text-xs font-bold text-white/85">{{ $entry->kind === 'entrada' ? 'Entrada' : 'Saída' }}@if ($entry->recurrence_id) · recorrente @endif · de {{ $entry->owner?->name }}</p>
    </section>

    @unless ($canEdit)
        <p class="rounded-2xl bg-[#FFF1D6] px-4 py-3 text-sm font-semibold text-[#6B4600]" data-testid="readonly-badge">Este lançamento é de {{ $entry->owner?->name }}. Você pode ver, mas só ele(a) altera.</p>
    @else
        <form wire:submit="save" class="nf-card flex flex-col gap-3 p-5">
            <div><label for="name" class="nf-label">Nome</label><input id="name" type="text" wire:model="name" class="nf-input" /></div>
            <div><label for="amount" class="nf-label">Valor</label><input id="amount" type="text" inputmode="decimal" wire:model="amount" x-data="moneyInput" x-on:blur="format($el)" class="nf-input" /></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label for="cat" class="nf-label">Categoria</label><select id="cat" wire:model="categoryId" class="nf-input">@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                @if ($entry->kind === 'saida')<div><label for="card" class="nf-label">Pago com</label><select id="card" wire:model="cardId" class="nf-input"><option value="">Dinheiro / débito</option>@foreach ($cards as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>@endif
            </div>

            @if ($entry->recurrence_id)
                <fieldset class="rounded-2xl bg-ground p-3" data-testid="scope">
                    <legend class="nf-label px-1">Aplicar a</legend>
                    @foreach (['this' => 'Só este mês', 'following' => 'Este e os próximos meses', 'all' => 'Todos, inclusive os anteriores'] as $value => $label)
                        <label class="flex min-h-11 items-center gap-2 text-sm font-bold"><input type="radio" wire:model.live="scope" value="{{ $value }}" class="size-4 accent-brand" /> {{ $label }}</label>
                    @endforeach
                    @if ($scope === 'all')
                        <label class="mt-1 flex min-h-11 items-center gap-2 rounded-xl bg-neg-soft px-3 text-sm font-bold text-[#8E2F20]"><input type="checkbox" wire:model="confirmAll" class="size-4 accent-brand" /> Entendo que isso muda o histórico, inclusive o que já foi pago.</label>
                    @endif
                </fieldset>
            @endif

            @if ($error) <p role="alert" class="text-sm font-bold text-neg" data-testid="form-error">{{ $error }}</p> @endif

            <button type="submit" class="nf-btn">Salvar alterações</button>
            <button type="button" wire:click="delete" wire:confirm="Excluir este lançamento?" class="nf-btn-ghost !border-neg !text-neg">Excluir</button>
        </form>
    @endunless
</div>
