<form wire:submit="save" class="mx-auto flex max-w-xl flex-col gap-4" aria-label="Novo lançamento">
    <header class="flex items-center justify-between">
        <a href="{{ route('statement', ['m' => $month]) }}" wire:navigate aria-label="Fechar" class="flex size-11 items-center justify-center rounded-full bg-white">✕</a>
        <h1 class="text-lg font-extrabold">Novo lançamento</h1>
        <span class="size-11"></span>
    </header>

    <div role="tablist" aria-label="Tipo" class="flex rounded-full bg-line p-1">
        <button type="button" role="tab" wire:click="$set('kind', 'saida')" wire:key="tab-saida" aria-selected="{{ $kind === 'saida' ? 'true' : 'false' }}" class="min-h-11 flex-1 rounded-full text-sm font-bold {{ $kind === 'saida' ? 'bg-neg text-white' : 'text-muted' }}">Saída</button>
        <button type="button" role="tab" wire:click="$set('kind', 'entrada')" aria-selected="{{ $kind === 'entrada' ? 'true' : 'false' }}" class="min-h-11 flex-1 rounded-full text-sm font-bold {{ $kind === 'entrada' ? 'bg-pos text-white' : 'text-muted' }}">Entrada</button>
        <button type="button" role="tab" wire:click="$set('kind', 'aporte')" aria-selected="{{ $kind === 'aporte' ? 'true' : 'false' }}" class="min-h-11 flex-1 rounded-full text-sm font-bold {{ $kind === 'aporte' ? 'bg-brand text-white' : 'text-muted' }}">Investimento</button>
    </div>

    <div class="text-center">
        <label for="amount" class="nf-label">Valor</label>
        <input id="amount" type="text" inputmode="decimal" wire:model="amount" placeholder="R$ 0,00" autocomplete="off" x-data="moneyInput" x-on:blur="format($el)"
               class="w-full bg-transparent text-center text-5xl font-extrabold tracking-tight {{ $kind === 'saida' ? 'text-neg' : ($kind === 'aporte' ? 'text-brand' : 'text-pos') }} placeholder:text-zinc-300 focus:outline-none" />
        @error('amount') <p class="text-sm font-bold text-neg">{{ $message }}</p> @enderror
    </div>

    <fieldset>
        <legend class="nf-label">Categoria</legend>
        <div class="flex flex-wrap gap-2">
            @foreach ($categories as $category)
                <button type="button" wire:click="$set('categoryId', {{ $category->id }})" aria-pressed="{{ $categoryId === $category->id ? 'true' : 'false' }}"
                        class="nf-chip {{ $categoryId === $category->id ? 'nf-chip-on' : '' }}">{{ $category->name }}</button>
            @endforeach
        </div>
        @error('categoryId') <p class="mt-1 text-sm font-bold text-neg">Escolha uma categoria.</p> @enderror
    </fieldset>

    <div>
        <label for="name" class="nf-label">Nome</label>
        <input id="name" type="text" wire:model="name" placeholder="{{ ['entrada' => 'Ex.: Salário de setembro', 'aporte' => 'Ex.: Tesouro Selic, CDB do banco', 'saida' => 'Ex.: Mercado'][$kind] }}" class="nf-input" />
        @error('name') <p class="mt-1 text-sm font-bold text-neg">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        @if ($kind === 'saida')
            <div>
                <label for="card" class="nf-label">Pago com</label>
                <select id="card" wire:model="cardId" class="nf-input"><option value="">Dinheiro / débito</option>@foreach ($cards as $card)<option value="{{ $card->id }}">{{ $card->name }}</option>@endforeach</select>
            </div>
        @endif
        <div @class(['col-span-2' => $kind !== 'saida'])>
            <label for="owner" class="nf-label">{{ ['entrada' => 'Quem recebe', 'aporte' => 'Quem investe', 'saida' => 'De quem'][$kind] }}</label>
            <select id="owner" wire:model="ownerId" class="nf-input">@foreach ($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select>
        </div>
    </div>

    <div class="flex flex-wrap gap-5">
        <label class="flex min-h-11 items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model="paid" class="size-5 accent-brand" /> {{ ['entrada' => 'Já recebido', 'aporte' => 'Já aplicado', 'saida' => 'Já está pago'][$kind] }}</label>
    </div>

    @if ($kind === 'saida' && $repeat === 'none')
        <div>
            <label for="due" class="nf-label">Vence em (opcional)</label>
            <input id="due" type="date" wire:model="dueOn" class="nf-input" />
            <p class="mt-1 text-xs text-muted">Com a data, a conta aparece em “Contas a vencer” no Início quando faltar até 7 dias.</p>
            @error('dueOn') <p class="mt-1 text-sm font-bold text-neg">{{ $message }}</p> @enderror
        </div>
    @endif

    <fieldset class="nf-card p-4">
        <legend class="nf-label px-1">Repetir</legend>
        <div class="grid grid-cols-2 gap-2">
            @foreach (collect(['none' => 'Não repete', 'monthly' => 'Todo mês', 'yearly' => 'Todo ano', 'installments' => 'Parcelado'])->when($kind !== 'saida', fn ($c) => $c->except('installments')) as $value => $label)
                <label class="flex min-h-11 items-center gap-2 rounded-2xl border-2 px-3 text-sm font-bold {{ $repeat === $value ? 'border-brand bg-brand-soft' : 'border-line' }}">
                    <input type="radio" wire:model.live="repeat" value="{{ $value }}" class="size-4 accent-brand" /> {{ $label }}
                </label>
            @endforeach
        </div>

        @if ($repeat !== 'none')
            <div class="mt-3 grid grid-cols-2 gap-3 rounded-2xl bg-ground p-3">
                <div><span class="nf-label">Começa em</span><div class="nf-input flex items-center font-bold">{{ $this->monthDate()->translatedFormat('m/Y') }}</div></div>
                <div><label for="day" class="nf-label">{{ ['entrada' => 'Recebe no dia', 'aporte' => 'Aplica no dia', 'saida' => 'Cobra no dia'][$kind] }}</label><input id="day" type="number" min="1" max="31" wire:model="chargeDay" class="nf-input" /></div>

                @if ($repeat === 'installments')
                    <div class="col-span-2"><label for="n" class="nf-label">Número de parcelas</label><input id="n" type="number" min="2" max="360" wire:model="installmentsTotal" class="nf-input" /></div>
                @else
                    <div class="col-span-2">
                        <span class="nf-label">Termina</span>
                        <div class="flex flex-wrap gap-2">
                            <label class="flex min-h-11 items-center gap-2 text-sm font-bold"><input type="radio" wire:model.live="endMode" value="never" class="size-4 accent-brand" /> Nunca</label>
                            <label class="flex min-h-11 items-center gap-2 text-sm font-bold"><input type="radio" wire:model.live="endMode" value="date" class="size-4 accent-brand" /> Em uma data</label>
                        </div>
                        @if ($endMode === 'date') <input type="date" wire:model="endsOn" aria-label="Data final" class="nf-input mt-1" /> @endif
                    </div>
                @endif
            </div>
        @endif
    </fieldset>

    @if ($error) <p role="alert" class="text-sm font-bold text-neg" data-testid="form-error">{{ $error }}</p> @endif

    <button type="submit" class="nf-btn w-full">Salvar</button>
</form>
