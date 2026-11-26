@php use App\Support\Money; @endphp
<div class="mx-auto flex max-w-3xl flex-col gap-4">
    <header><h1 class="text-2xl font-extrabold">Importar planilha</h1><p class="text-sm text-muted">Traga um mês da sua planilha “Orç - Mês”. Você confere antes de gravar.</p></header>

    @if ($done) <p role="status" class="rounded-2xl bg-pos-soft px-4 py-3 text-sm font-bold text-pos" data-testid="done">{{ $done }} <a href="{{ route('dashboard', ['m' => $month]) }}" class="underline">Ver o mês</a></p> @endif
    @if ($error) <p role="alert" class="rounded-2xl bg-neg-soft px-4 py-3 text-sm font-bold text-[#8E2F20]" data-testid="form-error">{{ $error }}</p> @endif

    @unless ($preview)
        <form wire:submit="preview" class="nf-card flex flex-col gap-4 p-5" aria-label="Escolher arquivo">
            <label for="file" class="flex cursor-pointer flex-col items-center gap-2 rounded-3xl border-2 border-dashed border-brand bg-brand-soft p-8 text-center">
                <span class="flex size-14 items-center justify-center rounded-full bg-brand text-white"><x-nav.icon name="upload" class="size-7" /></span>
                <span class="text-base font-extrabold">Escolher arquivo .xlsx</span>
                <span class="text-sm text-muted">{{ $file ? $file->getClientOriginalName() : 'Use a sua planilha “Orç - Mês”' }}</span>
                <input id="file" type="file" wire:model="file" accept=".xlsx" class="sr-only" />
            </label>
            <p wire:loading wire:target="file" class="rounded-2xl bg-brand-soft px-4 py-3 text-sm font-bold text-brand" role="status" data-testid="loading-file">Enviando e lendo o arquivo… aguarde.</p>
            @error('file') <p class="text-sm font-bold text-neg" data-testid="file-error">{{ $message }}</p> @enderror

            @if (count($sheets) > 1)
                <div><label for="sheet" class="nf-label">Qual aba importar?</label><select id="sheet" wire:model.live="sheet" class="nf-input" data-testid="sheet-select">@foreach ($sheets as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach</select>
                    <p class="mt-1 text-xs text-muted">Sua planilha tem {{ count($sheets) }} meses. Importe um de cada vez; o mês de destino é sugerido pelo título da aba.</p></div>
            @endif
            <div><label for="month" class="nf-label">Importar para o mês</label><input id="month" type="month" wire:model="month" class="nf-input" />@error('month') <p class="text-sm font-bold text-neg">{{ $message }}</p> @enderror</div>

            @if ($existingCount !== null)
                <label class="flex min-h-11 items-start gap-2 rounded-2xl bg-[#FFF1D6] px-4 py-3 text-sm font-semibold text-[#6B4600]" data-testid="merge-option">
                    <input type="checkbox" wire:model.live="merge" class="mt-0.5 size-5 shrink-0 accent-brand" />
                    <span>Importar mesmo assim, por cima dos {{ $existingCount }} lançamentos que o mês já tem. <span class="block text-xs font-normal">O que já existir igual (mesmo nome e valor) é pulado, sem duplicar.</span></span>
                </label>
            @else
                <p class="rounded-2xl bg-[#FFF1D6] px-4 py-3 text-sm font-medium text-[#6B4600]">Se o mês de destino já tiver lançamentos, você escolhe se quer importar por cima (o que já existir igual é pulado) ou usar outro mês.</p>
            @endif
            <button type="submit" class="nf-btn" wire:loading.attr="disabled" wire:target="preview,file"><span wire:loading.remove wire:target="preview">Ver prévia</span><span wire:loading wire:target="preview">Lendo a planilha…</span></button>
            <a href="{{ route('import.ofx') }}" wire:navigate class="text-center text-sm font-bold text-brand">Importar fatura do cartão (.ofx) ›</a>
        </form>
    @else
        <section class="nf-hero p-6" aria-label="Prévia" data-testid="preview">
            <p class="text-xs font-bold uppercase tracking-widest text-white/85">Fechamento da planilha</p>
            <p class="mt-1 text-4xl font-extrabold {{ $closing->closing < 0 ? 'text-[#FFB9AA]' : 'text-lime' }}" data-testid="preview-closing">{{ Money::signed($closing->closing) }}</p>
            <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                @foreach ($preview->cards as $card)<div class="rounded-2xl bg-white/12 px-3 py-2"><div class="text-xs text-white/85">{{ $card['name'] }}</div><b data-testid="preview-card">{{ Money::format($preview->cardTotal($card)) }}</b></div>@endforeach
                @foreach ($preview->people as $p)@if ($preview->personNet($p) !== 0)<div class="rounded-2xl bg-white/12 px-3 py-2"><div class="text-xs text-white/85">{{ $p['name'] }}</div><b data-testid="preview-person">{{ Money::signed($preview->personNet($p)) }}</b></div>@endif @endforeach
                @if ($preview->vale)<div class="rounded-2xl bg-white/12 px-3 py-2"><div class="text-xs text-white/85">Vale</div><b data-testid="preview-vale">{{ Money::format($preview->vale['amount']) }}</b></div>@endif
            </div>
        </section>

        @if (count($preview->cards))
            <section class="nf-card p-5" aria-label="Cartões da planilha" data-testid="due-days">
                <h2 class="text-base font-extrabold">Cartões</h2>
                <p class="mt-1 text-xs text-muted">Escolha a qual cartão já cadastrado cada um corresponde (para não criar repetido) e o dia em que a fatura vence.</p>
                <div class="mt-3 flex flex-col gap-3">
                    @foreach ($preview->cards as $ci => $card)
                        <div class="rounded-2xl bg-ground p-3" wire:key="map-{{ $ci }}" data-testid="card-map-{{ $ci }}">
                            <p class="text-sm font-extrabold">{{ $card['name'] }} <span class="text-xs font-semibold text-muted">na planilha · {{ Money::format($preview->cardTotal($card)) }}</span></p>
                            <div class="mt-2 grid grid-cols-3 gap-2">
                                <div class="col-span-2"><label for="map-{{ $ci }}" class="nf-label">É o cartão</label>
                                    <select id="map-{{ $ci }}" wire:model.live="cardMap.{{ $ci }}" class="nf-input" data-testid="card-select-{{ $ci }}"><option value="">Criar novo “{{ $card['name'] }}”</option>@foreach ($cards as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                                <div><label for="due-{{ $ci }}" class="nf-label">Vence dia</label><input id="due-{{ $ci }}" type="number" min="1" max="31" wire:model="dueDays.{{ $ci }}" placeholder="Dia" class="nf-input" data-testid="due-day-{{ $ci }}" /></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @foreach ($preview->warnings as $warning) <p class="rounded-2xl bg-[#FFF1D6] px-4 py-2 text-sm font-semibold text-[#6B4600]" data-testid="warning">{{ $warning }}</p> @endforeach

        <section class="nf-card p-3" aria-label="Itens" data-testid="items">
            <div class="flex items-center justify-between px-2 pb-1">
                <div><h2 class="text-base font-extrabold">Itens</h2><p class="text-xs text-muted">Desmarque o que não quer importar e ajuste nome, valor, categoria e dono.</p></div>
                <span class="flex shrink-0 gap-2"><button type="button" wire:click="selectAll(true)" class="text-xs font-bold text-brand">Marcar todos</button><button type="button" wire:click="selectAll(false)" class="text-xs font-bold text-muted">Limpar</button></span>
            </div>
            @php $secao = null; @endphp
            @forelse ($items as $key => $item)
                @if ($item['section'] !== $secao)
                    @php $secao = $item['section']; @endphp
                    <p class="px-2 pb-1 pt-3 text-xs font-extrabold uppercase tracking-wide text-muted">{{ $secao }}</p>
                @endif
                <div class="nf-row flex-wrap {{ $item['skip'] ? 'opacity-50' : '' }}" wire:key="item-{{ $key }}" data-testid="item">
                    <input type="checkbox" wire:model.live="edits.{{ $key }}.include" aria-label="Importar {{ $item['label'] }}" class="size-5 shrink-0 accent-brand" data-testid="item-include" />
                    <input type="text" wire:model.blur="edits.{{ $key }}.name" aria-label="Nome" class="nf-input !min-h-10 min-w-0 flex-1 !text-sm" />
                    <input type="text" inputmode="decimal" wire:model.blur="edits.{{ $key }}.amount" aria-label="Valor de {{ $item['label'] }}" x-data="moneyInput" x-on:blur="format($el)" class="nf-input !min-h-10 !w-28 !text-right !text-sm" />
                    <div class="flex w-full items-center gap-2 pl-7">
                        @if (in_array($item['type'], ['entrada', 'saida', 'cartao'], true))
                            <select wire:model="edits.{{ $key }}.category_id" aria-label="Categoria de {{ $item['label'] }}" class="nf-input !min-h-9 min-w-0 flex-1 !py-1 !text-sm">@foreach (($item['type'] === 'entrada' ? $catEntrada : $catSaida) as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</select>
                        @else
                            <span class="min-w-0 flex-1 text-xs text-muted">{{ $item['label'] }}</span>
                        @endif
                        @if (! empty($item['note'])) <span class="shrink-0 text-xs font-bold text-brand">{{ $item['note'] }}</span> @endif
                        <select wire:model="owners.{{ $key }}" aria-label="Dono de {{ $item['label'] }}" class="nf-input !min-h-9 !w-24 shrink-0 !py-1 !text-sm">@foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select>
                    </div>
                </div>
            @empty
                <p class="py-4 text-sm text-muted">Nenhum item para importar.</p>
            @endforelse
        </section>

        <div class="flex gap-3"><button type="button" wire:click="cancel" class="nf-btn-ghost flex-1">Cancelar</button><button type="button" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm" class="nf-btn flex-1" data-testid="confirm"><span wire:loading wire:target="confirm">Gravando… </span>Importar para {{ str(\Carbon\Carbon::createFromFormat('!Y-m', $month)->translatedFormat('F/Y'))->ucfirst() }}</button></div>
    @endunless
</div>
