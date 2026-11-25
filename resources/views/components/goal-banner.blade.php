@props(['level'])
<div role="status" data-testid="goal-banner" class="rounded-2xl px-4 py-3 text-sm font-bold {{ $level === '100' ? 'bg-neg-soft text-[#8E2F20]' : 'bg-[#FFF1D6] text-[#6B4600]' }}">
    {{ $level === '100' ? 'A meta do mês foi atingida (100%).' : 'Atenção: vocês já usaram 80% da meta do mês.' }}
    <a href="{{ route('goals') }}" wire:navigate class="underline">Ver metas</a>
</div>
