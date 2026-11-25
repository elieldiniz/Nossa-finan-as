<div class="mx-auto flex w-full max-w-md flex-col gap-4 p-4">
    <flux:heading size="xl">Convidar meu par</flux:heading>

    @if ($partner)
        <flux:callout variant="success" icon="check-circle" heading="{{ $partner->name }} já faz parte do casal." />
    @else
        <flux:text>Enviamos um link por e-mail. Ele vale por 7 dias e só pode ser usado uma vez.</flux:text>

        <form wire:submit="send" class="flex flex-col gap-4">
            <flux:input wire:model="email" type="email" label="E-mail do seu par" placeholder="par@email.com" required />
            <flux:button type="submit" variant="primary">Enviar convite</flux:button>
        </form>

        @if ($message)
            <flux:callout variant="success" icon="check-circle" heading="{{ $message }}" />
        @endif
        @if ($error)
            <flux:callout variant="danger" icon="exclamation-circle" heading="{{ $error }}" />
        @endif
    @endif
</div>
