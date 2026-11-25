<?php

namespace App\Livewire\Concerns;

use App\Services\OpenMonthService;
use App\Support\ViewContext;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;

/**
 * Mês e visão (Nós | Eu | Par) escolhidos, guardados na URL para voltar/compartilhar.
 */
trait WithMonthView
{
    #[Url(as: 'm')]
    public string $month = '';

    #[Url(as: 'v')]
    public string $view = ViewContext::NOS;

    public function mountWithMonthView(): void
    {
        $this->month = $this->monthDate()->format('Y-m');

        if (! in_array($this->view, [ViewContext::NOS, ViewContext::EU, ViewContext::PAR], true)) {
            $this->view = ViewContext::NOS;
        }
    }

    public function monthDate(): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m', $this->month ?: '1970-01');

        return ($date && $date->format('Y-m') === $this->month)
            ? $date
            : CarbonImmutable::now()->startOfMonth();
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthDate()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthDate()->addMonth()->format('Y-m');
    }

    public function setView(string $view): void
    {
        if (in_array($view, [ViewContext::NOS, ViewContext::EU, ViewContext::PAR], true)) {
            $this->view = $view;
        }
    }

    public function context(): ViewContext
    {
        return ViewContext::for(auth()->user(), $this->view);
    }

    /** Mês pronto: traz fixos, assinaturas e parcelas para o mês aberto (idempotente). */
    protected function openMonth(): void
    {
        app(OpenMonthService::class)->open(auth()->user()->couple_id, $this->monthDate());
    }
}
