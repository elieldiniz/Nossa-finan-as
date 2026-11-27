@php use App\Support\Money; @endphp
<div class="flex flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Cartões" subtitle="Cadastre uma vez: valem para todos os meses" />

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($summary['cards'] as $c)
            <a href="{{ route('cards.show', ['card' => $c['card']->id, 'm' => $month, 'v' => $view]) }}" wire:navigate wire:key="card-{{ $c['card']->id }}" class="nf-card block p-5" aria-label="Abrir cartão {{ $c['card']->name }}" data-testid="card-{{ $c['card']->id }}">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-ink text-lime"><x-nav.icon name="card" /></span>
                    <div class="min-w-0 flex-1"><h2 class="truncate text-base font-extrabold">{{ $c['card']->name }}</h2><p class="text-xs text-muted">{{ $isFuture ? 'Fatura prevista' : 'Fatura do mês' }}@if ($c['card']->due_day) · vence dia {{ $c['card']->due_day }}@endif</p></div>
                    <span class="text-lg font-extrabold" data-testid="invoice-total">{{ Money::format($c['total']) }}</span>
                </div>

                @if ($c['card']->limit_cents)
                    @php $used = min(100, intdiv($c['total'] * 100, max($c['card']->limit_cents, 1))); @endphp
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-line"><div class="h-full bg-brand" style="width: {{ $used }}%"></div></div>
                    <p class="mt-1 text-xs text-muted">{{ $used }}% do limite de {{ Money::format($c['card']->limit_cents) }}</p>
                @endif

                <div class="mt-3 flex items-center justify-between text-sm">
                    <span class="font-semibold text-muted" data-testid="card-count">{{ $c['lines']->count() }} {{ $c['lines']->count() === 1 ? 'compra' : 'compras' }}@if ($c['paid']) · fatura paga ✓ @endif</span>
                    <span class="font-bold text-brand">Abrir ›</span>
                </div>
            </a>
        @empty
            <p class="nf-card p-5 text-sm text-muted">Nenhum cartão cadastrado ainda. Cadastre abaixo.</p>
        @endforelse
    </div>

    <form wire:submit="addCard" class="nf-card flex flex-col gap-3 p-5" aria-label="{{ $editingId ? 'Editar cartão' : 'Novo cartão' }}">
        <h2 class="text-base font-extrabold">{{ $editingId ? 'Editar cartão' : 'Novo cartão' }}</h2>
        <div><label for="card-name" class="nf-label">Nome do cartão</label><input id="card-name" type="text" wire:model="name" placeholder="Ex.: Bradesco" class="nf-input" />@error('name') <p class="text-sm font-bold text-neg">{{ $message }}</p> @enderror</div>
        <div class="grid grid-cols-3 gap-3">
            <div><label for="closing" class="nf-label">Fecha dia</label><input id="closing" type="number" min="1" max="31" wire:model="closingDay" class="nf-input" /></div>
            <div><label for="due" class="nf-label">Vence dia</label><input id="due" type="number" min="1" max="31" wire:model="dueDay" class="nf-input" /></div>
            <div><label for="limit" class="nf-label">Limite</label><input id="limit" type="text" inputmode="decimal" wire:model="limit" placeholder="R$ 0,00" class="nf-input" /></div>
        </div>
        <button type="submit" class="nf-btn">{{ $editingId ? 'Salvar cartão' : '+ Novo cartão' }}</button>
    </form>

    @if ($cleanDone)
        <div role="status" class="flex items-center justify-between gap-3 rounded-2xl bg-brand-soft px-4 py-3 text-sm font-semibold text-brand" data-testid="clean-done">
            <span>{{ $cleanDone }}</span>
            <span class="flex shrink-0 gap-2">
                @if ($undo) <button type="button" wire:click="undoClean" class="nf-chip !min-h-9 !px-3 !text-xs" data-testid="clean-undo">Desfazer</button> @endif
                <button type="button" wire:click="dismissClean" aria-label="Fechar aviso" class="text-base font-extrabold">✕</button>
            </span>
        </div>
    @endif

    @if ($view !== 'par')
        <section class="nf-card p-5" aria-label="Limpeza em massa" data-testid="bulk">
            <h2 class="text-base font-extrabold">Limpeza em massa</h2>
            <p class="mt-1 text-sm text-muted">Para recomeçar ou corrigir uma importação errada. Só as compras que você lançou são apagadas.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @if (count($summary['cards']) > 0)
                <button type="button" wire:click="openCleanAll" class="nf-chip !text-neg" data-testid="open-clean-all">Excluir todas as faturas…</button>
                <button type="button" wire:click="openDeleteCards" class="nf-chip !text-neg" data-testid="open-delete-cards">Excluir todos os cartões…</button>
                @endif
                <button type="button" wire:click="openReset" class="nf-chip !border-neg !bg-neg-soft !text-[#8E2F20]" data-testid="open-reset">Apagar tudo e recomeçar…</button>
            </div>
        </section>
    @endif

    @if ($showCleanAll)
        <div class="fixed inset-0 z-[70] flex items-end justify-center bg-black/40 p-3 sm:items-center" role="dialog" aria-modal="true" aria-label="Excluir todas as faturas" data-testid="clean-all-dialog" wire:key="clean-all-dialog">
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-3xl bg-white p-5 shadow-xl">
                <h2 class="text-lg font-extrabold">Excluir todas as faturas</h2>
                <p class="mt-1 text-sm text-muted">Apaga as compras de <b>todos os cartões</b>. Os cartões continuam cadastrados.</p>
                <fieldset class="mt-3 flex flex-col gap-1">
                    <label class="flex min-h-11 items-center gap-2 rounded-2xl border-2 px-3 text-sm font-bold {{ $cleanScope === 'month' ? 'border-brand bg-brand-soft' : 'border-line' }}"><input type="radio" wire:model.live="cleanScope" value="month" class="size-4 accent-brand" /> Só de {{ $this->monthDate()->translatedFormat('F \d\e Y') }}</label>
                    <label class="flex min-h-11 items-center gap-2 rounded-2xl border-2 px-3 text-sm font-bold {{ $cleanScope === 'all' ? 'border-brand bg-brand-soft' : 'border-line' }}"><input type="radio" wire:model.live="cleanScope" value="all" class="size-4 accent-brand" /> De todos os meses</label>
                </fieldset>
                @php $totalCount = collect($cleanRows)->sum('count'); $totalCents = collect($cleanRows)->sum('total'); @endphp
                <div class="mt-3 rounded-2xl bg-ground p-3 text-sm" data-testid="clean-all-summary">
                    <p class="font-extrabold"><span data-testid="clean-all-count">{{ $totalCount }}</span> {{ $totalCount === 1 ? 'compra sua' : 'compras suas' }} · {{ Money::format($totalCents) }}</p>
                    <ul class="mt-1 text-muted">@foreach ($cleanRows as $row)<li class="flex justify-between"><span>{{ $row['name'] }}</span><span>{{ $row['count'] }} · {{ Money::format($row['total']) }}</span></li>@endforeach</ul>
                </div>
                <label class="mt-3 flex min-h-11 items-start gap-2 text-sm font-semibold"><input type="checkbox" wire:model="stopSeries" class="mt-0.5 size-5 shrink-0 accent-brand" data-testid="stop-series" /> <span>Parar também as parceladas e fixas daqui para frente <span class="block text-xs font-normal text-muted">Sem isso, a próxima parcela volta no mês seguinte.</span></span></label>
                <p class="mt-3 text-xs text-muted">Você poderá desfazer logo depois. Vale alimentação, pessoas e contas sem cartão não são mexidos.</p>
                <div class="mt-4 flex gap-3">
                    <button type="button" wire:click="closeDialogs" class="nf-btn-ghost flex-1">Cancelar</button>
                    <button type="button" wire:click="cleanAll" wire:loading.attr="disabled" @disabled($totalCount === 0) class="nf-btn flex-1 !bg-neg" data-testid="clean-all-confirm">Excluir {{ $totalCount }}</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showDeleteCards && $cardsPreview)
        <div class="fixed inset-0 z-[70] flex items-end justify-center bg-black/40 p-3 sm:items-center" role="dialog" aria-modal="true" aria-label="Excluir todos os cartões" data-testid="delete-cards-dialog" wire:key="delete-cards-dialog">
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-3xl bg-white p-5 shadow-xl">
                <h2 class="text-lg font-extrabold">Excluir todos os cartões</h2>
                <p class="mt-1 text-sm text-muted" data-testid="delete-cards-summary"><b>{{ count($cardsPreview['deletable']) }}</b> {{ count($cardsPreview['deletable']) === 1 ? 'cartão seu' : 'cartões seus' }}@if (count($cardsPreview['deletable'])) ({{ collect($cardsPreview['deletable'])->pluck('name')->implode(', ') }})@endif, com {{ $cardsPreview['purchases'] }} compras suas.</p>
                @if (count($cardsPreview['blocked']) || $cardsPreview['others'] > 0)
                    <p class="mt-2 rounded-2xl bg-[#FFF1D6] px-3 py-2 text-sm font-semibold text-[#6B4600]" data-testid="delete-cards-kept">
                        @if ($cardsPreview['others'] > 0) {{ $cardsPreview['others'] }} {{ $cardsPreview['others'] === 1 ? 'cartão do parceiro não será' : 'cartões do parceiro não serão' }} excluídos. @endif
                        @if (count($cardsPreview['blocked'])) {{ collect($cardsPreview['blocked'])->pluck('name')->implode(', ') }} {{ count($cardsPreview['blocked']) === 1 ? 'fica' : 'ficam' }} por ter{{ count($cardsPreview['blocked']) === 1 ? '' : 'em' }} compras do parceiro. @endif
                    </p>
                @endif
                <fieldset class="mt-3 flex flex-col gap-1">
                    <label class="flex items-start gap-2 rounded-2xl border-2 px-3 py-2 text-sm font-bold {{ $deleteMode === 'keep' ? 'border-brand bg-brand-soft' : 'border-line' }}"><input type="radio" wire:model.live="deleteMode" value="keep" class="mt-1 size-4 accent-brand" /> <span>Manter as compras como lançamentos sem cartão <span class="block text-xs font-normal text-muted">Elas continuam no Extrato e nos totais, só deixam de ser “do cartão”.</span></span></label>
                    <label class="flex items-start gap-2 rounded-2xl border-2 px-3 py-2 text-sm font-bold {{ $deleteMode === 'purge' ? 'border-neg bg-neg-soft' : 'border-line' }}"><input type="radio" wire:model.live="deleteMode" value="purge" class="mt-1 size-4 accent-brand" /> <span>Apagar as compras também <span class="block text-xs font-normal text-muted">As suas compras nesses cartões são apagadas e as parceladas/fixas param.</span></span></label>
                </fieldset>
                <p class="mt-3 rounded-2xl bg-neg-soft px-3 py-2 text-sm font-semibold text-[#8E2F20]">Excluir cartões <b>não dá para desfazer</b>.</p>
                <div class="mt-3"><label for="confirm-text" class="nf-label">Digite EXCLUIR para confirmar</label><input id="confirm-text" type="text" wire:model="confirmText" autocomplete="off" class="nf-input" data-testid="confirm-text" />@error('confirmText') <p class="mt-1 text-sm font-bold text-neg" data-testid="confirm-error">{{ $message }}</p> @enderror</div>
                <div class="mt-4 flex gap-3">
                    <button type="button" wire:click="closeDialogs" class="nf-btn-ghost flex-1">Cancelar</button>
                    <button type="button" wire:click="deleteAllCards" wire:loading.attr="disabled" class="nf-btn flex-1 !bg-neg" data-testid="delete-cards-confirm">Excluir cartões</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showReset && $resetPreview)
        <div class="fixed inset-0 z-[70] flex items-end justify-center bg-black/40 p-3 sm:items-center" role="dialog" aria-modal="true" aria-label="Apagar tudo e recomeçar" data-testid="reset-dialog" wire:key="reset-dialog">
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-3xl bg-white p-5 shadow-xl">
                <h2 class="text-lg font-extrabold text-neg">Apagar tudo e recomeçar</h2>
                <p class="mt-1 text-sm text-muted">Apaga <b>tudo o que você cadastrou</b>, para o app ficar limpo.</p>
                <ul class="mt-3 rounded-2xl bg-ground p-3 text-sm font-semibold" data-testid="reset-summary">
                    <li class="flex justify-between"><span>Lançamentos (extrato)</span><span data-testid="reset-entries">{{ $resetPreview['entries'] }}</span></li>
                    <li class="flex justify-between"><span>Contas recorrentes e parceladas</span><span>{{ $resetPreview['recurrences'] }}</span></li>
                    <li class="flex justify-between"><span>Cartões</span><span>{{ $resetPreview['cards'] }}</span></li>
                    <li class="flex justify-between"><span>Pendências com pessoas</span><span>{{ $resetPreview['debts'] }}</span></li>
                    <li class="flex justify-between"><span>Gastos do vale</span><span>{{ $resetPreview['vale_spends'] }}</span></li>
                </ul>
                <p class="mt-3 text-xs text-muted">Não mexe no que o parceiro cadastrou, na sua conta, nas categorias nem nas metas. Cartões com compras do parceiro não são apagados.</p>
                <p class="mt-2 rounded-2xl bg-neg-soft px-3 py-2 text-sm font-semibold text-[#8E2F20]">Isso <b>não dá para desfazer</b>.</p>
                <div class="mt-3"><label for="reset-phrase" class="nf-label">Digite APAGAR TUDO para confirmar</label><input id="reset-phrase" type="text" wire:model="resetPhrase" autocomplete="off" class="nf-input" data-testid="reset-phrase" />@error('resetPhrase') <p class="mt-1 text-sm font-bold text-neg" data-testid="reset-phrase-error">{{ $message }}</p> @enderror</div>
                <div class="mt-3"><label for="reset-password" class="nf-label">Sua senha</label><input id="reset-password" type="password" wire:model="resetPassword" autocomplete="current-password" class="nf-input" data-testid="reset-password" />@error('resetPassword') <p class="mt-1 text-sm font-bold text-neg" data-testid="reset-password-error">{{ $message }}</p> @enderror</div>
                <div class="mt-4 flex gap-3">
                    <button type="button" wire:click="closeDialogs" class="nf-btn-ghost flex-1">Cancelar</button>
                    <button type="button" wire:click="resetAll" wire:loading.attr="disabled" class="nf-btn flex-1 !bg-neg" data-testid="reset-confirm">Apagar tudo</button>
                </div>
            </div>
        </div>
    @endif
</div>
