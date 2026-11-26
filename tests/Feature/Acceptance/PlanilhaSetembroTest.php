<?php

use App\Livewire\Home\HomePage;
use App\Livewire\Import\ImportPage;
use App\Services\MonthSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Fixtures\OrcMesFixture;

it('ponta a ponta: importar Setembro e ver Madrinha +115, Bradesco 600, Cresol 0, Vale 2.250 e fechamento +1.765 (−485 sem o vale)', function () {
    [$eliel] = makeCouple();
    $arquivo = UploadedFile::fake()->createWithContent('planilha.xlsx', file_get_contents(OrcMesFixture::setembro()));

    Livewire::actingAs($eliel)->test(ImportPage::class)->set('month', '2026-09')->set('file', $arquivo)->call('preview')->call('confirm');

    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-09')
        ->assertSee('+R$ 1.765,00')->assertSee('Entrou mais do que saiu')
        ->assertSeeHtmlInOrder(['Bradesco', 'R$ 600,00', 'Cresol', 'R$ 0,00'])
        ->assertSee('Madrinha')->assertSee('+R$ 115,00')
        ->assertSee('R$ 2.250,00');
});

it('o resultado é o mesmo em "Nós" e somando as visões Eliel e Ana', function () {
    [$eliel] = cenarioPlanilha();
    $service = app(MonthSummary::class);
    $mes = CarbonImmutable::parse('2026-09-01');

    $nos = $service->build($eliel, $mes, 'nos')['closing']->closing;
    $eu = $service->build($eliel, $mes, 'eu')['closing']->closing;
    $par = $service->build($eliel, $mes, 'par')['closing']->closing;

    expect($nos)->toBe(176500)->and($eu + $par)->toBe($nos);
});
