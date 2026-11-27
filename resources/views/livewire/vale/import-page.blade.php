@php use App\Support\Money; @endphp
<div class="mx-auto flex max-w-3xl flex-col gap-4">
    <header>
        <a href="{{ route('vale') }}" wire:navigate class="text-sm font-bold text-brand">‹ Vale alimentação</a>
        <h1 class="mt-1 text-2xl font-extrabold">Importar extrato do vale alimentação</h1>
        <p class="text-sm text-muted">Use o <b>.ofx</b> ou o <b>.pdf</b> do extrato do cartão de vale. Os gastos entram no seu vale alimentação, no mês de cada data. Você confere antes de gravar.</p>
    </header>

    @if ($done) <p role="status" class="rounded-2xl bg-pos-soft px-4 py-3 text-sm font-bold text-pos" data-testid="done">{{ $done }} <a href="{{ route('vale') }}" wire:navigate class="underline">Ver o vale</a></p> @endif
    @if ($error) <p role="alert" class="rounded-2xl bg-neg-soft px-4 py-3 text-sm font-bold text-[#8E2F20]" data-testid="form-error">{{ $error }}</p> @endif
    @error('file') <p role="alert" class="rounded-2xl bg-neg-soft px-4 py-3 text-sm font-bold text-[#8E2F20]" data-testid="file-error">{{ $message }}</p> @enderror

    @unless ($statement)
        <section class="nf-card flex flex-col gap-4 p-5" aria-label="Escolher arquivo">
            <label for="vfile" class="flex cursor-pointer flex-col items-center gap-2 rounded-3xl border-2 border-dashed border-brand bg-brand-soft p-8 text-center">
                <span class="flex size-14 items-center justify-center rounded-full bg-brand text-white"><x-nav.icon name="upload" class="size-7" /></span>
                <span class="text-base font-extrabold">Escolher arquivo .ofx ou .pdf</span>
                <span class="text-sm text-muted">{{ $file ? $file->getClientOriginalName() : 'Extrato do vale alimentação' }}</span>
                <input id="vfile" type="file" wire:model="file" accept=".ofx,.pdf" class="sr-only" />
            </label>
            <p wire:loading wire:target="file" class="rounded-2xl bg-brand-soft px-4 py-3 text-sm font-bold text-brand" role="status">Enviando e lendo o arquivo… aguarde.</p>
            <p class="text-xs text-muted">Importar o mesmo extrato duas vezes não duplica nada. Compras que já existem iguais (mesma data, nome e valor) são puladas. O PDF é lido por linhas como “05/11  Mercado  R$ 45,00”.</p>
        </section>
    @else
        <section class="nf-card flex flex-col gap-3 p-5" aria-label="Destino" data-testid="vale-import-preview">
            <p class="text-sm font-semibold text-muted">{{ $statement['bank'] ?? 'Extrato' }} · {{ \Carbon\Carbon::parse($statement['start'])->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($statement['end'])->format('d/m/Y') }} · {{ count($lines) }} lançamentos</p>

            @if (! $hasVale || $changeVale)
                <div>
                    <label for="vale-amount" class="nf-label">Valor do seu vale alimentação (entra todo mês)</label>
                    <input id="vale-amount" type="text" inputmode="decimal" wire:model="valeAmount" x-data="moneyInput" x-on:blur="format($el)" placeholder="R$ 2.250,00" class="nf-input" data-testid="vale-amount" />
                    <p class="mt-1 text-xs text-muted">{{ $hasVale ? 'Muda o valor do vale daqui para frente.' : 'Você ainda não tem vale cadastrado: informe o valor para criar.' }}@if ($credits > 0) O extrato mostra recarga de {{ Money::format($credits) }}.@endif</p>
                </div>
            @else
                <label class="flex min-h-11 items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model.live="changeVale" class="size-5 accent-brand" data-testid="change-vale" /> Também alterar o valor do meu vale{{ $credits > 0 ? ' (o extrato mostra recarga de '.Money::format($credits).')' : '' }}</label>
            @endif
        </section>

        <section class="nf-hero p-6" aria-label="Resumo">
            <p class="text-xs font-bold uppercase tracking-widest text-white/85">A importar</p>
            <p class="mt-1 text-4xl font-extrabold" data-testid="vale-import-total">{{ Money::format($total) }}</p>
            <p class="mt-1 text-sm font-semibold text-white/85" data-testid="vale-import-count">{{ $count }} gastos @if ($monthsCount > 1) em {{ $monthsCount }} meses @elseif ($firstMonth) em {{ $firstMonth }} @endif @if ($credits > 0) · {{ Money::format($credits) }} de recargas não entram @endif</p>
        </section>

        <section class="nf-card p-3" aria-label="Gastos">
            <div class="flex items-center justify-between px-2 pb-2">
                <h2 class="text-base font-extrabold">Gastos</h2>
                <span class="flex gap-2"><button type="button" wire:click="selectAll(true)" class="text-xs font-bold text-brand">Marcar todos</button><button type="button" wire:click="selectAll(false)" class="text-xs font-bold text-muted">Limpar</button></span>
            </div>
            @foreach ($lines as $i => $line)
                <div class="nf-row flex-wrap {{ $line['credit'] || $line['duplicate'] ? 'opacity-60' : '' }}" wire:key="vi-{{ $i }}" data-testid="vale-import-line">
                    <input type="checkbox" wire:model.live="lines.{{ $i }}.include" @disabled($line['credit'] || $line['duplicate']) aria-label="Importar {{ $line['name'] }}" class="size-5 shrink-0 accent-brand" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold">{{ $line['name'] }}</span>
                        <span class="text-xs text-muted">{{ \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}@if ($line['credit']) · recarga/crédito: não entra @elseif ($line['duplicate']) · já importado @endif</span>
                    </span>
                    <span class="text-sm font-extrabold {{ $line['credit'] ? 'text-pos' : '' }}">{{ $line['credit'] ? '+' : '' }}{{ Money::format(abs($line['amount'])) }}</span>
                    @unless ($line['credit'] || $line['duplicate'])
                        <select wire:model="lines.{{ $i }}.category_id" aria-label="Categoria de {{ $line['name'] }}" class="nf-input !min-h-9 !w-full !py-1 !text-sm"><option value="">Sem categoria</option>@foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</select>
                    @endunless
                </div>
            @endforeach
        </section>

        <div class="flex gap-3">
            <button type="button" wire:click="cancel" class="nf-btn-ghost flex-1">Cancelar</button>
            <button type="button" wire:click="confirm" wire:loading.attr="disabled" class="nf-btn flex-1" data-testid="vale-import-confirm"><span wire:loading wire:target="confirm">Gravando… </span>Importar {{ $count }} gastos</button>
        </div>
    @endunless
</div>
