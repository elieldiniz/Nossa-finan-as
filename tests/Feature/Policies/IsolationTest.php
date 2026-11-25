<?php

use App\Models\Entry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

it('quem é de fora do casal não lê nada nem altera', function () {
    [$eliel] = makeCouple();
    [$carlos] = makeCouple('Carlos', 'Dora');
    $item = makeEntry($eliel);

    expect(Gate::forUser($carlos)->allows('view', $item))->toBeFalse()
        ->and(Gate::forUser($carlos)->allows('update', $item))->toBeFalse()
        ->and(Gate::forUser($carlos)->allows('delete', $item))->toBeFalse();

    $this->actingAs($carlos);
    expect(Entry::count())->toBe(0);
});

it('o par não consegue editar nem apagar pelo caminho direto e o item fica idêntico', function () {
    [$eliel, $ana] = makeCouple();
    $item = makeEntry($eliel, ['name' => 'Original', 'amount_cents' => 5000]);
    $antes = $item->fresh()->toArray();

    expect(fn () => Gate::forUser($ana)->authorize('update', $item))->toThrow(AuthorizationException::class)
        ->and(fn () => Gate::forUser($ana)->authorize('delete', $item))->toThrow(AuthorizationException::class)
        ->and($item->fresh()->toArray())->toBe($antes);
});

it('visitante é redirecionado ao login nas rotas de dados', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
