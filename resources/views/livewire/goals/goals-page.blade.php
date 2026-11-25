@php use App\Support\Money; @endphp
<div class="mx-auto flex max-w-2xl flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Metas e investimentos" subtitle="Defina quanto querem gastar e quanto querem investir. O app avisa antes de passar." :switcher="false" />

    @if ($error) <p role="alert" class="rounded-2xl bg-neg-soft px-4 py-3 text-sm font-bold text-[#8E2F20]" data-testid="form-error">{{ $error }}</p> @endif

    {{-- 1. Meta de gastos do mês --}}
    <section class="nf-card p-5" aria-label="Meta de gastos do mês" data-testid="general-goal">
        <h2 class="text-base font-extrabold">Meta de gastos do mês</h2>

        @if ($general)
            @php $pct = min(100, intdiv($totalSpent * 100, max($general->limit_cents, 1))); @endphp
            <p class="mt-2 text-sm"><b class="text-2xl font-extrabold" data-testid="spent">{{ Money::format($totalSpent) }}</b> <span class="text-muted">de {{ Money::format($general->limit_cents) }} ({{ $pct }}%)</span></p>
            <div class="mt-2 h-3 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full {{ $pct >= 100 ? 'bg-neg' : ($pct >= 80 ? 'bg-[#E8A34B]' : 'bg-brand') }}" style="width: {{ $pct }}%"></div></div>

            @if ($totalSpent >= $general->limit_cents)
                <p class="mt-2 text-sm font-bold text-neg" data-testid="remaining">Passou {{ Money::format($totalSpent - $general->limit_cents) }} da meta.</p>
            @else
                <p class="mt-2 text-sm font-semibold" data-testid="remaining">Faltam {{ Money::format($remaining) }} para a meta @if ($perDay !== null)— dá cerca de <b data-testid="per-day">{{ Money::format($perDay) }}</b> por dia até o fim do mês @endif</p>
            @endif

            @if ($generalLevel !== 'nenhum') <div class="mt-2"><x-goal-banner :level="$generalLevel" /></div> @endif

            <form wire:submit="saveGeneral" class="mt-3 flex gap-2" aria-label="Alterar a meta de gastos">
                <input type="text" inputmode="decimal" wire:model="generalLimit" aria-label="Novo valor da meta de gastos" placeholder="Novo valor" x-data="moneyInput" x-on:blur="format($el)" class="nf-input" />
                <button type="submit" class="nf-btn !px-5">Alterar</button>
            </form>
            <button type="button" wire:click="removeGoal({{ $general->id }})" class="nf-chip mt-2 !text-neg">Remover meta</button>
        @else
            <p class="mt-1 text-sm text-muted">Escolha um valor para o mês inteiro. Quando os gastos chegarem a 80% você recebe um aviso amarelo no Início, e a 100% um aviso vermelho.</p>
            @if ($suggestion)
                <button type="button" wire:click="useSuggestion({{ $suggestion }})" class="nf-btn-ghost mt-3 w-full" data-testid="suggestion">Usar {{ Money::format($suggestion) }} <span class="text-xs font-semibold text-muted">(média dos últimos meses)</span></button>
                <p class="my-2 text-center text-xs text-muted">ou digite o seu valor</p>
            @endif
            <form wire:submit="saveGeneral" class="mt-3 flex gap-2" aria-label="Definir a meta de gastos">
                <input type="text" inputmode="decimal" wire:model="generalLimit" aria-label="Meta de gastos do mês" placeholder="Ex.: R$ 5.000,00" x-data="moneyInput" x-on:blur="format($el)" class="nf-input" />
                <button type="submit" class="nf-btn !px-5">Definir</button>
            </form>
        @endif
    </section>

    {{-- 2. Meta de investimento --}}
    <section class="nf-card p-5" aria-label="Meta de investimento" data-testid="invest-goal">
        <h2 class="text-base font-extrabold">Investimentos</h2>
        <div class="mt-3 grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-brand-soft px-3 py-2"><div class="text-xs font-semibold text-brand">Investido neste mês</div><div class="font-extrabold text-brand" data-testid="invested">{{ Money::format($invested) }}</div></div>
            <div class="rounded-2xl bg-ground px-3 py-2"><div class="text-xs font-semibold text-muted">Total já investido</div><div class="font-extrabold" data-testid="accumulated">{{ Money::format($accumulated) }}</div></div>
        </div>

        @if ($investGoal)
            @php $ipct = min(100, intdiv($invested * 100, max($investGoal->limit_cents, 1))); @endphp
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $ipct }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full bg-brand" style="width: {{ $ipct }}%"></div></div>
            <p class="mt-1 text-sm font-semibold" data-testid="invest-progress">{{ $ipct }}% da meta de investir {{ Money::format($investGoal->limit_cents) }} por mês @if ($invested >= $investGoal->limit_cents)— meta batida! @else— faltam {{ Money::format($investGoal->limit_cents - $invested) }} @endif</p>
            <button type="button" wire:click="removeGoal({{ $investGoal->id }})" class="nf-chip mt-2 !text-neg">Remover meta de investir</button>
        @else
            <p class="mt-3 text-sm text-muted">Quanto vocês querem investir por mês? Registre cada aporte pelo botão <b>+</b>, escolhendo “Investimento”.</p>
        @endif

        <form wire:submit="saveInvest" class="mt-3 flex gap-2" aria-label="Meta de investimento">
            <input type="text" inputmode="decimal" wire:model="investLimit" aria-label="Meta de investir por mês" placeholder="{{ $investGoal ? 'Novo valor' : 'Ex.: R$ 1.000,00 por mês' }}" x-data="moneyInput" x-on:blur="format($el)" class="nf-input" />
            <button type="submit" class="nf-btn !px-5">{{ $investGoal ? 'Alterar' : 'Definir' }}</button>
        </form>
    </section>

    {{-- 3. Limites por categoria (só as que a pessoa escolher) --}}
    <section class="nf-card p-5" aria-label="Limites por categoria" data-testid="category-limits">
        <h2 class="text-base font-extrabold">Limite por categoria <span class="text-xs font-semibold text-muted">(opcional)</span></h2>
        <p class="mt-1 text-sm text-muted">Para vigiar uma categoria específica, como Mercado ou Lazer.</p>

        @forelse ($rows as $row)
            <div class="nf-row flex-wrap" wire:key="cat-{{ $row['category']->id }}" data-testid="cat-{{ $row['category']->id }}">
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold">{{ $row['category']->name }}</span>
                    <span class="text-xs {{ $row['level'] === '100' ? 'font-extrabold text-neg' : ($row['level'] === '80' ? 'font-extrabold text-[#8A5A00]' : 'text-muted') }}">{{ Money::format($row['spent']) }}@if ($row['goal']) de {{ Money::format($row['goal']->limit_cents) }}@else · sem limite @endif</span>
                </span>
                @if ($row['goal'])
                    <button type="button" wire:click="removeGoal({{ $row['goal']->id }})" aria-label="Remover limite de {{ $row['category']->name }}" class="nf-chip !min-h-9 !px-3 !text-xs !text-neg">Remover</button>
                @endif
            </div>
        @empty
            <p class="mt-2 rounded-2xl border-2 border-dashed border-zinc-300 p-4 text-sm text-muted">Nenhum limite por categoria ainda.</p>
        @endforelse

        <form wire:submit="addCategoryGoal" class="mt-3 flex flex-wrap items-end gap-2" aria-label="Adicionar limite">
            <div class="min-w-36 flex-1"><label for="gcat" class="nf-label">Categoria</label><select id="gcat" wire:model="addCategoryId" class="nf-input"><option value="">Escolha…</option>@foreach ($available as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="min-w-32 flex-1"><label for="glimit" class="nf-label">Limite do mês</label><input id="glimit" type="text" inputmode="decimal" wire:model="addCategoryLimit" placeholder="R$ 0,00" x-data="moneyInput" x-on:blur="format($el)" class="nf-input" /></div>
            <button type="submit" class="nf-btn !px-5">+ Adicionar limite</button>
        </form>
    </section>

    <p class="px-2 text-center text-xs text-muted">As contas que vencem nos próximos dias aparecem em <a href="{{ route('dashboard') }}" wire:navigate class="font-bold text-brand">Início</a>, em “Contas a vencer”.</p>
</div>
