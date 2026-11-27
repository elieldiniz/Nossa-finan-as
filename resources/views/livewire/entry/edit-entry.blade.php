@php use App\Support\Money; @endphp
<div class="mx-auto flex max-w-xl flex-col gap-4">
    <header class="flex items-center gap-3">
        <a href="{{ route('statement', ['m' => $entry->month->format('Y-m')]) }}" wire:navigate aria-label="Voltar" class="flex size-11 items-center justify-center rounded-full bg-white text-xl">‹</a>
        <h1 class="text-xl font-extrabold">Lançamento</h1>
    </header>

    <section class="nf-hero p-6 text-center">
        <p class="text-sm font-bold text-white/85">{{ $entry->name }}@if ($entry->installment_label) · parcela {{ $entry->installment_label }}@endif</p>
        <p class="mt-1 text-4xl font-extrabold">{{ $entry->kind === 'entrada' ? '+' : '−' }}{{ Money::format($entry->amount_cents) }}</p>
        <p class="mt-2 text-xs font-bold text-white/85">{{ ['entrada' => 'Entrada', 'saida' => 'Saída', 'aporte' => 'Investimento'][$entry->kind] }}@if ($entry->recurrence_id) · recorrente @endif · de {{ $entry->owner?->name }}</p>
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

            <div class="grid grid-cols-2 gap-3">
                @if ($entry->kind === 'saida')
                    <div><label for="due" class="nf-label">Vence em</label>@if ($entry->recurrence_id)<p class="mb-1 text-xs text-muted" data-testid="due-hint">Vale só para este mês. Para mudar o dia dos próximos meses, use “Cobra no dia” em Regra da conta.</p>@endif<input id="due" type="date" wire:model="dueOn" class="nf-input" data-testid="due-input" />@error('dueOn') <p class="mt-1 text-sm font-bold text-neg">{{ $message }}</p> @enderror</div>
                @endif
                @unless ($entry->recurrence_id)
                    <div><label for="month" class="nf-label">Mês</label><input id="month" type="month" wire:model="month" class="nf-input" data-testid="month-input" />@error('month') <p class="mt-1 text-sm font-bold text-neg">{{ $message }}</p> @enderror</div>
                @endunless
            </div>
            <label class="flex min-h-11 items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model="paid" class="size-5 accent-brand" data-testid="paid-input" /> {{ ['entrada' => 'Já recebido', 'aporte' => 'Já aplicado', 'saida' => 'Já está pago'][$entry->kind] }}</label>

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

            @if ($entry->recurrence_id)
                <fieldset class="rounded-2xl bg-ground p-3" data-testid="schedule">
                    <legend class="nf-label px-1">Regra da conta <span class="font-normal">(vale para todos os meses)</span></legend>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label for="day" class="nf-label">Cobra no dia</label><input id="day" type="number" min="1" max="31" wire:model="chargeDay" class="nf-input" data-testid="day-input" />@error('chargeDay') <p class="mt-1 text-sm font-bold text-neg">{{ $message }}</p> @enderror</div>
                        @if ($this->isInstallment())
                            <div><label for="n" class="nf-label">Nº de parcelas</label><input id="n" type="number" min="2" max="360" wire:model="installmentsTotal" class="nf-input" data-testid="installments-input" />@error('installmentsTotal') <p class="mt-1 text-sm font-bold text-neg">{{ $message }}</p> @enderror</div>
                        @else
                            <div class="col-span-2">
                                <span class="nf-label">Termina</span>
                                <div class="flex flex-wrap gap-2">
                                    <label class="flex min-h-11 items-center gap-2 text-sm font-bold"><input type="radio" wire:model.live="endMode" value="never" class="size-4 accent-brand" /> Nunca</label>
                                    <label class="flex min-h-11 items-center gap-2 text-sm font-bold"><input type="radio" wire:model.live="endMode" value="date" class="size-4 accent-brand" /> Em uma data</label>
                                </div>
                                @if ($endMode === 'date') <input type="date" wire:model="endsOn" aria-label="Data final" class="nf-input mt-1" data-testid="ends-input" /> @endif
                            </div>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-muted">Meses já pagos nunca são apagados.</p>
                </fieldset>
            @endif

            @if ($error) <p role="alert" class="text-sm font-bold text-neg" data-testid="form-error">{{ $error }}</p> @endif

            <button type="submit" class="nf-btn">Salvar alterações</button>
            <button type="button" wire:click="delete" wire:confirm="Excluir este lançamento?" class="nf-btn-ghost !border-neg !text-neg">Excluir</button>
        </form>
    @endunless
</div>
