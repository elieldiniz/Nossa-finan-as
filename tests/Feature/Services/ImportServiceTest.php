<?php

use App\Import\ImportPayload;
use App\Import\OrcMesImport;
use App\Models\Card;
use App\Models\Entry;
use App\Models\PersonDebt;
use App\Models\Recurrence;
use App\Models\Vale;
use App\Services\ImportService;
use App\Services\MonthSummary;
use App\Services\OpenMonthService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Tests\Fixtures\OrcMesFixture;

function payloadSetembro(array $extra = []): ImportPayload
{
    return (new OrcMesImport)->parse(OrcMesFixture::setembro($extra));
}

it('grava Setembro: Bradesco 600, Madrinha +115, Vale 2.250 e fechamento +1.765 no app (−485 sem o vale)', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $setembro = CarbonImmutable::parse('2026-09-01');

    $contagem = app(ImportService::class)->import($eliel, payloadSetembro(), $setembro);

    $resumo = app(MonthSummary::class)->build($eliel, $setembro);
    expect($contagem['entries'])->toBe(2)->and($contagem['debts'])->toBe(2)
        ->and($resumo['closing']->closing)->toBe(176500)
        ->and($resumo['cards'][0]['total'])->toBe(60000)
        ->and(Vale::first()->amount_cents)->toBe(225000)
        ->and(Card::pluck('name')->sort()->values()->all())->toBe(['Bradesco', 'Cresol']);
});

it('os fixos importados continuam nos meses seguintes', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    app(ImportService::class)->import($eliel, payloadSetembro(), CarbonImmutable::parse('2026-09-01'));

    app(OpenMonthService::class)->open($eliel->couple_id, CarbonImmutable::parse('2026-10-01'));

    expect(Entry::where('month', '2026-10-01')->count())->toBe(2);
});

it('parcelado "2/10" cria a regra a partir da parcela 1 e marca 2/10 no mês', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $payload = payloadSetembro(['bradesco' => [['2/10', 'Geladeira', 150]]]);

    app(ImportService::class)->import($eliel, $payload, CarbonImmutable::parse('2026-09-01'));

    $regra = Recurrence::where('name', 'Geladeira')->first();
    expect($regra->frequency)->toBe('installments')->and($regra->installments_total)->toBe(10)
        ->and($regra->starts_on->toDateString())->toBe('2026-08-01')
        ->and(Entry::where('name', 'Geladeira')->first()->installment_label)->toBe('2/10');
});

it('mês que já tem dados é recusado e fica idêntico', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    makeEntry($eliel, ['month' => '2026-09-01']);
    $antes = Entry::count();

    expect(fn () => app(ImportService::class)->import($eliel, payloadSetembro(), CarbonImmutable::parse('2026-09-01')))
        ->toThrow(InvalidArgumentException::class)
        ->and(Entry::count())->toBe($antes)->and(PersonDebt::count())->toBe(0);
});

it('falha no meio reverte tudo', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $payload = new ImportPayload(
        entries: [['type' => 'saida', 'name' => 'Boa', 'amount' => 1000, 'paid' => false]],
        cards: [['name' => 'Itaú', 'lines' => [['kind' => 'fixo', 'name' => 'Ruim', 'amount' => 0, 'installment' => null]]]],
    );

    expect(fn () => app(ImportService::class)->import($eliel, $payload, CarbonImmutable::parse('2026-09-01')))->toThrow(QueryException::class)
        ->and(Entry::count())->toBe(0)->and(Card::count())->toBe(0)->and(Recurrence::count())->toBe(0);
});

it('dono fora do casal é recusado; dono do par é aceito', function () {
    [$eliel, $ana] = makeCouple();
    [$carlos] = makeCouple('Carlos', 'Dora');
    $this->actingAs($eliel);
    $payload = payloadSetembro(['entradas' => [['Salário', 100, false]]]);

    expect(fn () => app(ImportService::class)->import($eliel, $payload, CarbonImmutable::parse('2026-09-01'), ['e0' => $carlos->id]))
        ->toThrow(InvalidArgumentException::class);

    app(ImportService::class)->import($eliel, $payload, CarbonImmutable::parse('2026-09-01'), ['e0' => $ana->id]);
    expect(Entry::where('name', 'Salário')->first()->owner_id)->toBe($ana->id);
});

it('cartão com data de última parcela: vira mensal até aquele mês; data que já passou entra só neste mês', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $payload = new ImportPayload(entries: [], cards: [['name' => 'Sicoob', 'lines' => [
        ['kind' => 'fixo', 'name' => 'Armário', 'amount' => 10000, 'installment' => null, 'ends_on' => '2026-12-08'],
        ['kind' => 'fixo', 'name' => 'Já acabou', 'amount' => 5000, 'installment' => null, 'ends_on' => '2020-01-05'],
        ['kind' => 'fixo', 'name' => 'Google Fotos', 'amount' => 1500, 'installment' => null, 'ends_on' => null],
    ]]], people: [], vale: null);

    app(ImportService::class)->import($eliel, $payload, CarbonImmutable::parse('2026-10-01'));
    app(OpenMonthService::class)->openRange($eliel->couple_id, CarbonImmutable::parse('2026-10-01'), 6);

    $meses = fn (string $nome) => Entry::where('name', $nome)->orderBy('month')->get()->map(fn ($e) => $e->month->format('Y-m'))->all();

    expect($meses('Armário'))->toBe(['2026-10', '2026-11', '2026-12'])      // até a última parcela
        ->and($meses('Já acabou'))->toBe(['2026-10'])                        // só o mês importado
        ->and($meses('Google Fotos'))->toBe(['2026-10', '2026-11', '2026-12', '2027-01', '2027-02', '2027-03']);
});
