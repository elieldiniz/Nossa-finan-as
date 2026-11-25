<?php

use App\Models\Entry;
use Illuminate\Support\Facades\Gate;

it('os dois leem, só o dono altera ou apaga', function () {
    [$eliel, $ana] = makeCouple();
    $item = makeEntry($eliel);

    expect(Gate::forUser($eliel)->allows('view', $item))->toBeTrue()
        ->and(Gate::forUser($ana)->allows('view', $item))->toBeTrue()
        ->and(Gate::forUser($eliel)->allows('update', $item))->toBeTrue()
        ->and(Gate::forUser($eliel)->allows('delete', $item))->toBeTrue()
        ->and(Gate::forUser($ana)->allows('update', $item))->toBeFalse()
        ->and(Gate::forUser($ana)->allows('delete', $item))->toBeFalse();
});

it('qualquer membro pode criar, inclusive em nome do par', function () {
    [$eliel] = makeCouple();

    expect(Gate::forUser($eliel)->allows('create', Entry::class))->toBeTrue();
});

it('cadastros compartilhados (cartão, categoria) os dois editam', function () {
    [$eliel, $ana] = makeCouple();
    $categoria = categoryOf($eliel->couple_id);

    expect(Gate::forUser($ana)->allows('update', $categoria))->toBeTrue();
});
