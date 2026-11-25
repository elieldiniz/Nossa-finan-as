<?php

namespace App\Domain;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Agenda de uma recorrência (RF-10, RF-11, RF-18).
 *
 * Tipos:
 *  - 'monthly'      todo mês (fixo ou assinatura);
 *  - 'yearly'       uma vez por ano, no mês do início;
 *  - 'installments' parcelado: termina sozinho depois de `installmentsTotal` parcelas.
 *
 * O término (`endsOn`) vale por MÊS: se termina em 23/12/2027, dezembro de 2027 ainda ocorre
 * e janeiro de 2028 não. Sem `endsOn` a recorrência nunca acaba.
 */
final class RecurrenceSchedule
{
    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    public const INSTALLMENTS = 'installments';

    private CarbonImmutable $start;

    private ?CarbonImmutable $endsOn;

    public function __construct(
        string|CarbonImmutable $startsOn,
        public readonly string $frequency = self::MONTHLY,
        public readonly ?int $dayOfMonth = null,
        string|CarbonImmutable|null $endsOn = null,
        public readonly ?int $installmentsTotal = null,
    ) {
        if (! in_array($frequency, [self::MONTHLY, self::YEARLY, self::INSTALLMENTS], true)) {
            throw new InvalidArgumentException("Frequência desconhecida: {$frequency}");
        }

        if ($frequency === self::INSTALLMENTS && ($installmentsTotal === null || $installmentsTotal < 1)) {
            throw new InvalidArgumentException('Parcelado precisa de um número de parcelas maior que zero.');
        }

        $this->start = CarbonImmutable::parse($startsOn)->startOfDay();
        $this->endsOn = $endsOn === null ? null : CarbonImmutable::parse($endsOn)->startOfDay();
    }

    /** A ocorrência cai neste mês? */
    public function occursIn(int $year, int $month): bool
    {
        $offset = $this->monthsSinceStart($year, $month);

        if ($offset < 0) {
            return false;
        }

        if ($this->endsOn !== null && $this->monthIndex($year, $month) > $this->monthIndex($this->endsOn->year, $this->endsOn->month)) {
            return false;
        }

        return match ($this->frequency) {
            self::MONTHLY => true,
            self::YEARLY => $offset % 12 === 0,
            self::INSTALLMENTS => $offset < $this->installmentsTotal,
        };
    }

    /** Data da ocorrência no mês (dia limitado ao fim do mês), ou null se não ocorre. */
    public function dateIn(int $year, int $month): ?CarbonImmutable
    {
        if (! $this->occursIn($year, $month)) {
            return null;
        }

        $first = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $day = min($this->dayOfMonth ?? $this->start->day, $first->daysInMonth);

        return $first->day($day);
    }

    /** "2/3" para parcelado; null nos demais tipos ou fora do período. */
    public function installmentLabel(int $year, int $month): ?string
    {
        if ($this->frequency !== self::INSTALLMENTS || ! $this->occursIn($year, $month)) {
            return null;
        }

        return ($this->monthsSinceStart($year, $month) + 1).'/'.$this->installmentsTotal;
    }

    /** Todos os meses 'YYYY-MM' em que ocorre dentro de um intervalo. */
    public function monthsBetween(int $fromYear, int $fromMonth, int $toYear, int $toMonth): array
    {
        $result = [];
        $i = $this->monthIndex($fromYear, $fromMonth);
        $last = $this->monthIndex($toYear, $toMonth);

        for (; $i <= $last; $i++) {
            $year = intdiv($i, 12);
            $month = $i % 12 + 1;

            if ($this->occursIn($year, $month)) {
                $result[] = sprintf('%04d-%02d', $year, $month);
            }
        }

        return $result;
    }

    private function monthsSinceStart(int $year, int $month): int
    {
        return $this->monthIndex($year, $month) - $this->monthIndex($this->start->year, $this->start->month);
    }

    private function monthIndex(int $year, int $month): int
    {
        return $year * 12 + ($month - 1);
    }
}
