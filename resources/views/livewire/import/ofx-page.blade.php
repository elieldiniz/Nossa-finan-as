@php use App\Support\Money; @endphp
<div class="mx-auto flex max-w-3xl flex-col gap-4">
    <header><h1 class="text-2xl font-extrabold">Importar fatura do cartão</h1><p class="text-sm text-muted">Use o arquivo <b>.ofx</b> do banco (Nubank, por exemplo) ou a <b>fatura em .pdf</b> do Ourocard. Você confere as compras antes de gravar.</p></header>

    @if ($done) <p role="status" class="rounded-2xl bg-pos-soft px-4 py-3 text-sm font-bold text-pos" data-testid="done">{{ $done }} <a href="{{ route('statement', ['m' => $month]) }}" wire:navigate class="underline">Ver o Extrato</a></p> @endif
    @if ($error) <p role="alert" class="rounded-2xl bg-neg-soft px-4 py-3 text-sm font-bold text-[#8E2F20]" data-testid="form-error">{{ $error }}</p> @endif
    @error('file') <p role="alert" class="rounded-2xl bg-neg-soft px-4 py-3 text-sm font-bold text-[#8E2F20]" data-testid="file-error">{{ $message }}</p> @enderror

    @unless ($statement)
        <section class="nf-card flex flex-col gap-4 p-5" aria-label="Escolher arquivo">
            <label for="ofx" class="flex cursor-pointer flex-col items-center gap-2 rounded-3xl border-2 border-dashed border-brand bg-brand-soft p-8 text-center">
                <span class="flex size-14 items-center justify-center rounded-full bg-brand text-white"><x-nav.icon name="upload" class="size-7" /></span>
                <span class="text-base font-extrabold">Escolher arquivo .ofx ou .pdf</span>
                <span class="text-sm text-muted">{{ $file ? $file->getClientOriginalName() : 'Extrato .ofx ou fatura .pdf do cartão' }}</span>
                <input id="ofx" type="file" wire:model="file" accept=".ofx,.pdf" class="sr-only" />
            </label>
            <p class="text-xs text-muted">Compras parceladas (“Parcela 2/5”) entram como parceladas e continuam nos meses seguintes. Importar o mesmo arquivo duas vezes não duplica nada.</p>
            <a href="{{ route('import') }}" wire:navigate class="text-sm font-bold text-brand">Importar planilha “Orç - Mês” em vez disso ›</a>
        </section>
    @else
        <section class="nf-card flex flex-col gap-3 p-5" aria-label="Destino" data-testid="ofx-preview">
            <p class="text-sm font-semibold text-muted">{{ $statement['bank'] }} · @if (! empty($statement['start'])){{ \Carbon\Carbon::parse($statement['start'])->format('d/m') }} a @endif{{ \Carbon\Carbon::parse($statement['end'])->format('d/m/Y') }} · {{ count($lines) }} lançamentos</p>
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label for="card" class="nf-label">Cartão</label>
                    <select id="card" wire:model.live="cardId" class="nf-input" data-testid="card-select"><option value="">Criar novo cartão…</option>@foreach ($cards as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                    @unless ($cardId)<input type="text" wire:model="newCardName" placeholder="Nome do novo cartão" aria-label="Nome do novo cartão" class="nf-input mt-2" data-testid="new-card" />@endunless
                </div>
                <div class="col-span-2">
                    <label for="due" class="nf-label">Vence dia</label>
                    <input id="due" type="number" min="1" max="31" wire:model="dueDay" placeholder="Ex.: 7" class="nf-input" data-testid="due-day" />
                    <p class="mt-1 text-xs text-muted">{{ empty($statement['due_date']) ? 'O arquivo .ofx não traz o vencimento. Com o dia (por exemplo 7), a fatura aparece em Contas a vencer.' : 'Vem da fatura em PDF. Com o dia gravado no cartão, a fatura aparece em Contas a vencer.' }}</p>
                    @error('dueDay') <p class="mt-1 text-sm font-bold text-neg">{{ $message }}</p> @enderror
                </div>
                <div><label for="month" class="nf-label">Mês da fatura</label><input id="month" type="month" wire:model.live="month" class="nf-input" data-testid="month-input" /></div>
                <div><label for="owner" class="nf-label">De quem</label><select id="owner" wire:model="ownerId" class="nf-input">@foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
            </div>
            @if (! empty($statement['due_date']))
                <p class="rounded-2xl bg-brand-soft px-3 py-2 text-sm font-semibold text-brand" data-testid="pdf-info">Vence em {{ \Carbon\Carbon::parse($statement['due_date'])->format('d/m/Y') }} · fechou em {{ \Carbon\Carbon::parse($statement['closing_date'])->format('d/m/Y') }}@if ($limitCents) · limite {{ Money::format($limitCents) }}@endif</p>
            @endif
            @if (! empty($statement['total']))
                @php $confere = $purchasesTotal === $statement['total']; @endphp
                <p class="rounded-2xl px-3 py-2 text-sm font-bold {{ $confere ? 'bg-pos-soft text-pos' : 'bg-[#FFF1D6] text-[#6B4600]' }}" data-testid="pdf-check">
                    {{ $confere ? '✓ Confere: as compras lidas somam' : 'Atenção: as compras lidas somam' }} {{ Money::format($purchasesTotal) }} e a fatura diz {{ Money::format($statement['total']) }}.
                </p>
            @endif
            @foreach ($statement['warnings'] ?? [] as $warning) <p class="rounded-2xl bg-[#FFF1D6] px-3 py-2 text-sm font-semibold text-[#6B4600]" data-testid="pdf-warning">{{ $warning }}</p> @endforeach
            @if (count($holderInclude) > 1)
                <div class="rounded-2xl bg-ground p-3" data-testid="holders">
                    <p class="text-sm font-extrabold">Cartões da fatura</p>
                    @foreach ($statement['holders'] as $h)
                        <label class="mt-1 flex min-h-11 items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model.live="holderInclude.{{ $h['card'] }}" class="size-5 accent-brand" data-testid="holder-{{ $h['card'] }}" /> Cartão {{ $h['card'] }} · {{ $h['name'] }} <span class="ml-auto text-muted">{{ Money::format($h['subtotal']) }}</span></label>
                    @endforeach
                    <p class="mt-1 text-xs text-muted">Compras de cartões adicionais levam “· cartão NNNN” no nome.</p>
                </div>
            @endif
            <p class="text-xs text-muted">O mês da fatura é onde as compras aparecem no app: a fatura de novembro (compras até 30/11) vence em dezembro.</p>
        </section>

        <section class="nf-hero p-6" aria-label="Resumo">
            <p class="text-xs font-bold uppercase tracking-widest text-white/85">A importar</p>
            <p class="mt-1 text-4xl font-extrabold" data-testid="ofx-total">{{ Money::format($total) }}</p>
            <p class="mt-1 text-sm font-semibold text-white/85" data-testid="ofx-count">{{ $count }} compras @if ($payments > 0) · {{ Money::format($payments) }} de pagamentos/créditos não entram @endif</p>
        </section>

        <section class="nf-card p-3" aria-label="Compras">
            <div class="flex items-center justify-between px-2 pb-2">
                <h2 class="text-base font-extrabold">Compras</h2>
                <span class="flex gap-2"><button type="button" wire:click="selectAll(true)" class="text-xs font-bold text-brand">Marcar todas</button><button type="button" wire:click="selectAll(false)" class="text-xs font-bold text-muted">Limpar</button></span>
            </div>
            @foreach ($lines as $i => $line)
                <div class="nf-row flex-wrap {{ $line['credit'] || $line['duplicate'] ? 'opacity-60' : '' }}" wire:key="ofx-{{ $i }}" data-testid="ofx-line">
                    <input type="checkbox" wire:model.live="lines.{{ $i }}.include" @disabled($line['credit'] || $line['duplicate']) aria-label="Importar {{ $line['name'] }}" class="size-5 shrink-0 accent-brand" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold">{{ $line['name'] }}@if ($line['installment']) <span class="font-semibold text-brand">· parcela {{ $line['installment'][0] }}/{{ $line['installment'][1] }}</span>@endif</span>
                        <span class="text-xs text-muted">{{ \Carbon\Carbon::parse($line['date'])->format('d/m') }}@if ($line['credit']) · pagamento/crédito: não entra @elseif ($line['duplicate']) · já importada @endif</span>
                    </span>
                    <span class="text-sm font-extrabold {{ $line['credit'] ? 'text-pos' : '' }}">{{ $line['credit'] ? '+' : '' }}{{ Money::format(abs($line['amount'])) }}</span>
                    @unless ($line['credit'] || $line['duplicate'])
                        <select wire:model="lines.{{ $i }}.category_id" aria-label="Categoria de {{ $line['name'] }}" class="nf-input !min-h-9 !w-full !py-1 !text-sm">@foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</select>
                    @endunless
                </div>
            @endforeach
        </section>

        <div class="flex gap-3">
            <button type="button" wire:click="cancel" class="nf-btn-ghost flex-1">Cancelar</button>
            <button type="button" wire:click="confirm" class="nf-btn flex-1" wire:loading.attr="disabled" data-testid="ofx-confirm">Importar {{ $count }} compras</button>
        </div>
    @endunless
</div>
