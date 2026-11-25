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
            @error('file') <p class="text-sm font-bold text-neg" data-testid="file-error">{{ $message }}</p> @enderror

            <div><label for="month" class="nf-label">Importar para o mês</label><input id="month" type="month" wire:model="month" class="nf-input" />@error('month') <p class="text-sm font-bold text-neg">{{ $message }}</p> @enderror</div>

            <p class="rounded-2xl bg-[#FFF1D6] px-4 py-3 text-sm font-medium text-[#6B4600]">Se o mês já tiver dados, a importação é recusada: limpe o mês antes ou escolha outro.</p>
            <button type="submit" class="nf-btn" wire:loading.attr="disabled">Ver prévia</button>
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

        @foreach ($preview->warnings as $warning) <p class="rounded-2xl bg-[#FFF1D6] px-4 py-2 text-sm font-semibold text-[#6B4600]" data-testid="warning">{{ $warning }}</p> @endforeach

        <section class="nf-card px-5 py-2" aria-label="Itens e donos">
            <p class="py-2 text-xs text-muted">Por padrão tudo fica no seu nome. Troque o dono de cada item se precisar.</p>
            @forelse ($items as $key => $item)
                <div class="nf-row" wire:key="item-{{ $key }}"><span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">{{ $item['label'] }}</span><span class="text-xs text-muted">{{ $item['section'] }}</span></span><span class="text-sm font-extrabold">{{ Money::format($item['amount']) }}</span>
                    <select wire:model="owners.{{ $key }}" aria-label="Dono de {{ $item['label'] }}" class="nf-input !min-h-10 !w-28">@foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
            @empty
                <p class="py-4 text-sm text-muted">Nenhum lançamento para trocar de dono.</p>
            @endforelse
        </section>

        <div class="flex gap-3"><button type="button" wire:click="cancel" class="nf-btn-ghost flex-1">Cancelar</button><button type="button" wire:click="confirm" class="nf-btn flex-1" data-testid="confirm">Importar para {{ str(\Carbon\Carbon::createFromFormat('!Y-m', $month)->translatedFormat('F/Y'))->ucfirst() }}</button></div>
    @endunless
</div>
