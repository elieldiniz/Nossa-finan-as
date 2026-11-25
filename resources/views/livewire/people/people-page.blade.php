@php use App\Support\Money; $readOnly = $this->context()->isReadOnly(); @endphp
<div class="flex flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Pessoas" subtitle="Quem você deve e quem te deve. O saldo entra no mês." />

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($people as $entry)
            @php $person = $entry['person']; @endphp
            <section class="nf-card p-5" wire:key="person-{{ $person->id }}" aria-label="{{ $person->name }}" data-testid="person-{{ $person->id }}">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 items-center justify-center rounded-full bg-pos-soft text-lg font-extrabold text-pos">{{ mb_substr($person->name, 0, 1) }}</span>
                    <div class="flex-1"><h2 class="text-base font-extrabold">{{ $person->name }}</h2><p class="text-xs text-muted">{{ $entry['net'] === 0 ? 'Sem pendências' : 'Saldo do mês' }}</p></div>
                    <span class="rounded-full px-3 py-1 text-sm font-extrabold {{ $entry['net'] > 0 ? 'bg-pos-soft text-pos' : ($entry['net'] < 0 ? 'bg-neg-soft text-[#8E2F20]' : 'bg-ground text-muted') }}" data-testid="net">{{ Money::signed($entry['net']) }}</span>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div class="rounded-2xl bg-neg-soft px-3 py-2"><div class="text-xs font-semibold text-[#7A2415]">A pagar</div><div class="font-extrabold text-[#7A2415]" data-testid="pay">{{ Money::format($entry['pay']) }}</div></div>
                    <div class="rounded-2xl bg-pos-soft px-3 py-2"><div class="text-xs font-semibold text-pos">A receber</div><div class="font-extrabold text-pos" data-testid="receive">{{ Money::format($entry['receive']) }}</div></div>
                </div>

                @foreach ($entry['debts'] as $debt)
                    <div class="mt-3 border-t border-ground pt-2" wire:key="debt-{{ $debt->id }}">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="flex-1 font-bold">{{ $debt->direction === 'pagar' ? 'Pagar' : 'Receber' }}@if ($debt->description) · {{ $debt->description }}@endif</span>
                            <b class="{{ $debt->direction === 'pagar' ? '' : 'text-pos' }}">{{ $debt->direction === 'pagar' ? '−' : '+' }}{{ Money::format($debt->amount_cents) }}</b>
                        </div>
                        @if ($debt->installments->isNotEmpty())
                            @foreach ($debt->installments as $i)
                                <div class="flex min-h-11 items-center gap-3 pl-2 text-sm">
                                    <button type="button" @if($i->paid_month || $readOnly || $debt->owner_id !== auth()->id()) disabled @else wire:click="payInstallment({{ $i->id }})" @endif
                                            role="checkbox" aria-checked="{{ $i->paid_month ? 'true' : 'false' }}" aria-label="Parcela {{ $i->position }} paga"
                                            class="flex size-6 items-center justify-center rounded-full border-2 {{ $i->paid_month ? 'border-brand bg-brand text-white' : 'border-zinc-300' }}">@if ($i->paid_month)✓@endif</button>
                                    <span class="flex-1">Parcela {{ $i->position }}/{{ $debt->installments->count() }}</span><span class="font-bold">{{ Money::format($i->amount_cents) }}</span>
                                </div>
                            @endforeach
                        @elseif (! $readOnly && $debt->owner_id === auth()->id() && ! ($debt->settled_month && $debt->settled_month->format('Y-m') < $monthKey))
                            <button type="button" wire:click="settle({{ $debt->id }})" class="nf-chip mt-1">{{ $debt->settled_month ? 'Quitada ✓' : 'Marcar como quitada' }}</button>
                        @endif
                    </div>
                @endforeach

                @unless ($readOnly)
                    <button type="button" wire:click="removePerson({{ $person->id }})" wire:confirm="Excluir esta pessoa e as pendências dela?" class="nf-chip mt-3 !text-neg">Excluir pessoa</button>
                @endunless
            </section>
        @empty
            <p class="nf-card p-5 text-sm text-muted lg:col-span-2">Nenhuma pessoa ainda. Cadastre abaixo.</p>
        @endforelse
    </div>

    @unless ($readOnly)
        <form wire:submit="addPerson" class="nf-card flex items-end gap-3 p-5" aria-label="Nova pessoa">
            <div class="flex-1"><label for="pname" class="nf-label">Nova pessoa</label><input id="pname" type="text" wire:model="personName" placeholder="Ex.: Madrinha" class="nf-input" />@error('personName') <p class="text-sm font-bold text-neg">{{ $message }}</p> @enderror</div>
            <button type="submit" class="nf-btn !min-h-12">+ Pessoa</button>
        </form>

        @if ($people->isNotEmpty())
            <form wire:submit="addDebt" class="nf-card flex flex-col gap-3 p-5" aria-label="Nova pendência">
                <h2 class="text-base font-extrabold">Nova pendência</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div><label for="who" class="nf-label">Pessoa</label><select id="who" wire:model="personId" class="nf-input"><option value="">Escolha…</option>@foreach ($people as $e)<option value="{{ $e['person']->id }}">{{ $e['person']->name }}</option>@endforeach</select></div>
                    <div><label for="dir" class="nf-label">Tipo</label><select id="dir" wire:model="direction" class="nf-input"><option value="receber">A receber</option><option value="pagar">A pagar</option></select></div>
                    <div><label for="val" class="nf-label">Valor</label><input id="val" type="text" inputmode="decimal" wire:model="amount" placeholder="R$ 0,00" class="nf-input" /></div>
                    <div><label for="parc" class="nf-label">Parcelas (opcional)</label><input id="parc" type="number" min="2" wire:model="installments" class="nf-input" /></div>
                </div>
                <div><label for="desc" class="nf-label">Descrição (opcional)</label><input id="desc" type="text" wire:model="description" class="nf-input" /></div>
                <button type="submit" class="nf-btn">Salvar pendência</button>
            </form>
        @endif
    @endunless

    @if ($error) <p role="alert" class="text-sm font-bold text-neg" data-testid="form-error">{{ $error }}</p> @endif
</div>
