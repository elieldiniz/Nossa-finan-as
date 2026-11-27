<?php

use App\Import\OfxParser;
use App\Models\User;
use App\Models\Vale;
use App\Models\ValeSpend;
use App\Services\Vale\ValeStatement;
use App\Support\ViewContext;
use Carbon\CarbonImmutable;
use Smalot\PdfParser\Parser;

function gasto(User $u, Vale $vale, string $mes, string $nome, int $cents, ?string $categoria = null, ?string $dia = null): ValeSpend
{
    $s = new ValeSpend(['vale_id' => $vale->id, 'owner_id' => $u->id, 'name' => $nome, 'amount_cents' => $cents, 'month' => $mes.'-01', 'spent_on' => $dia,
        'category_id' => $categoria ? categoryOf($u->couple_id, $categoria)->id : null]);
    $s->couple_id = $u->couple_id;
    $s->save();

    return $s;
}

function cenarioVale(): array
{
    [$eliel, $ana] = makeCouple();
    test()->actingAs($eliel);
    $vale = makeVale($eliel, 225000);
    gasto($eliel, $vale, '2026-11', 'Mercadão', 45000, 'Mercado', '2026-11-05');
    gasto($eliel, $vale, '2026-11', 'Padaria', 3200, 'Mercado', '2026-11-08');
    gasto($eliel, $vale, '2026-11', 'Pizza', 6000, 'Lazer', '2026-11-12');
    gasto($eliel, $vale, '2026-10', 'Açougue', 12000, 'Mercado', '2026-10-20');

    return [$eliel, $ana, $vale];
}

it('o extrato do mês junta o crédito do vale, os gastos por categoria e a sobra', function () {
    [$eliel] = cenarioVale();

    $s = app(ValeStatement::class)->build(ViewContext::for($eliel, 'eu'), CarbonImmutable::parse('2026-11-01'));
    $mes = $s['vales'][0]['months'][0];

    expect($s['title'])->toBe('Vale alimentação')->and($s['period'])->toBe('Novembro de 2026')->and(count($s['vales']))->toBe(1)
        ->and($mes['credit'])->toBe(225000)->and($mes['spent'])->toBe(54200)->and($mes['remaining'])->toBe(170800)
        ->and(array_column($mes['groups'], 'category'))->toBe(['Mercado', 'Lazer'])
        ->and($mes['groups'][0]['subtotal'])->toBe(48200)->and(array_column($mes['groups'][0]['rows'], 'date'))->toBe(['2026-11-05', '2026-11-08']);
});

it('"todos os meses" traz cada mês, com a sobra de cada um (a sobra não passa para o seguinte)', function () {
    [$eliel] = cenarioVale();
    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00'));

    $s = app(ValeStatement::class)->build(ViewContext::for($eliel, 'eu'), null);
    $meses = collect($s['vales'][0]['months'])->keyBy('month');

    expect($meses->keys()->all())->toContain('2026-10', '2026-11')
        ->and($meses['2026-10']['remaining'])->toBe(213000)->and($meses['2026-11']['remaining'])->toBe(170800)
        ->and($s['vales'][0]['spent'])->toBe(66200);
});

it('OFX: formato de extrato do banco, que o próprio importador lê de volta com os mesmos valores', function () {
    [$eliel] = cenarioVale();
    $this->actingAs($eliel);

    $resposta = $this->get(route('vale.export', ['format' => 'ofx', 'm' => '2026-11', 'v' => 'eu']))->assertOk();
    $corpo = $resposta->getContent();

    expect($resposta->headers->get('Content-Type'))->toContain('ofx')->and($resposta->headers->get('Content-Disposition'))->toContain('vale-alimentacao-2026-11.ofx')
        ->and($corpo)->toStartWith('OFXHEADER:100')->toContain('<BANKMSGSRSV1>')->toContain('<CURDEF>BRL</CURDEF>')->toContain('<TRNTYPE>CREDIT</TRNTYPE>')->toContain('<TRNTYPE>DEBIT</TRNTYPE>')
        ->toContain('<BALAMT>1708.00</BALAMT>');

    $lido = (new OfxParser)->parse($corpo);
    $debitos = array_filter($lido['transactions'], fn ($t) => $t['amount'] < 0);
    $creditos = array_filter($lido['transactions'], fn ($t) => $t['amount'] > 0);

    expect(count($lido['transactions']))->toBe(4)->and(count($debitos))->toBe(3)->and(-array_sum(array_column($debitos, 'amount')))->toBe(54200)
        ->and(array_sum(array_column($creditos, 'amount')))->toBe(225000)->and(array_column($debitos, 'name'))->toContain('Mercadão', 'Padaria', 'Pizza')
        ->and($lido['start'])->toBe('2026-11-01')->and($lido['end'])->toBe('2026-11-30');
});

it('OFX: os identificadores são únicos e estáveis (reimportar não duplica) e & e < no nome saem escapados', function () {
    [$eliel, , $vale] = cenarioVale();
    gasto($eliel, $vale, '2026-11', 'Lanche & Cia <promo>', 1000, null, '2026-11-15');
    $this->actingAs($eliel);

    $a = $this->get(route('vale.export', ['format' => 'ofx', 'm' => '2026-11', 'v' => 'eu']))->getContent();
    $b = $this->get(route('vale.export', ['format' => 'ofx', 'm' => '2026-11', 'v' => 'eu']))->getContent();
    $ids = array_column((new OfxParser)->parse($a)['transactions'], 'id');

    expect(array_values(array_unique($ids)))->toHaveCount(count($ids))->and(preg_replace('/<DTSERVER>.*<\/DTSERVER>/', '', $a))->toBe(preg_replace('/<DTSERVER>.*<\/DTSERVER>/', '', $b))
        ->and($a)->toContain('Lanche &amp; Cia &lt;promo&gt;')->and(collect((new OfxParser)->parse($a)['transactions'])->pluck('name'))->toContain('Lanche & Cia <promo>');
});

it('PDF: arquivo PDF de verdade com o título, o resumo, os gastos por categoria e a sobra', function () {
    [$eliel] = cenarioVale();
    $this->actingAs($eliel);

    $resposta = $this->get(route('vale.export', ['format' => 'pdf', 'm' => '2026-11', 'v' => 'eu']))->assertOk();
    $pdf = $resposta->getContent();

    expect($resposta->headers->get('Content-Type'))->toBe('application/pdf')->and($resposta->headers->get('Content-Disposition'))->toContain('vale-alimentacao-2026-11.pdf')
        ->and(substr($pdf, 0, 5))->toBe('%PDF-');

    $texto = (new Parser)->parseContent($pdf)->getText();

    expect($texto)->toContain('Vale alimentação')->toContain('Novembro de 2026')->toContain('Mercado')->toContain('Lazer')->toContain('Mercadão')->toContain('Padaria')
        ->toContain('R$ 2.250,00')->toContain('R$ 482,00')->toContain('R$ 542,00')->toContain('R$ 1.708,00')->toContain('05/11')->not->toContain('Açougue'); // outubro não entra no extrato de novembro
});

it('PDF de todos os meses traz outubro e novembro', function () {
    [$eliel] = cenarioVale();
    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00'));
    $this->actingAs($eliel);

    $texto = (new Parser)->parseContent($this->get(route('vale.export', ['format' => 'pdf', 'm' => 'tudo', 'v' => 'eu']))->getContent())->getText();

    expect($texto)->toContain('Todos os meses')->toContain('Outubro de 2026')->toContain('Novembro de 2026')->toContain('Açougue');
});

it('a exportação respeita a visão: "Eu" só o seu vale, "Nós" os dois, e exige login', function () {
    [$eliel, $ana] = cenarioVale();
    $valeAna = makeVale($ana, 150000);
    gasto($ana, $valeAna, '2026-11', 'Feira da Ana', 2500, 'Mercado', '2026-11-03');
    $this->actingAs($eliel);

    $eu = $this->get(route('vale.export', ['format' => 'ofx', 'm' => '2026-11', 'v' => 'eu']))->getContent();
    $nos = $this->get(route('vale.export', ['format' => 'ofx', 'm' => '2026-11', 'v' => 'nos']))->getContent();
    $par = $this->get(route('vale.export', ['format' => 'ofx', 'm' => '2026-11', 'v' => 'par']))->getContent();

    expect($eu)->not->toContain('Feira da Ana')->and(substr_count($eu, '<STMTTRNRS>'))->toBe(1)
        ->and($nos)->toContain('Feira da Ana')->toContain('Mercadão')->and(substr_count($nos, '<STMTTRNRS>'))->toBe(2)
        ->and($par)->toContain('Feira da Ana')->not->toContain('Mercadão');

    auth()->logout();
    $this->get(route('vale.export', ['format' => 'pdf']))->assertRedirect(route('login'));
});

it('mês inválido e formato desconhecido são recusados; outro casal não vê dados de vocês', function () {
    [$eliel] = cenarioVale();
    [$outro] = makeCouple('Outro', 'Casal');

    $this->actingAs($eliel)->get(route('vale.export', ['format' => 'pdf', 'm' => '2026-13']))->assertStatus(422);
    $this->actingAs($eliel)->get('/vale/exportar/xlsx')->assertNotFound();

    $outroOfx = $this->actingAs($outro)->get(route('vale.export', ['format' => 'ofx', 'm' => '2026-11', 'v' => 'nos']))->getContent();
    expect($outroOfx)->not->toContain('Mercadão')->and(substr_count($outroOfx, '<STMTTRNRS>'))->toBe(0);
});

it('sem vale cadastrado o PDF e o OFX ainda saem (vazios)', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    $this->get(route('vale.export', ['format' => 'pdf']))->assertOk();
    $this->get(route('vale.export', ['format' => 'ofx']))->assertOk();
});
