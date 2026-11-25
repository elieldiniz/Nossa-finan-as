<?php

use App\Domain\GoalAlert;

it('meta de 1.000: 799,99 não avisa, 800 avisa 80% e 1.000 avisa 100%', function () {
    expect(GoalAlert::evaluate(79999, 100000))->toBe(GoalAlert::NONE)
        ->and(GoalAlert::evaluate(80000, 100000))->toBe(GoalAlert::WARN_80)
        ->and(GoalAlert::evaluate(99999, 100000))->toBe(GoalAlert::WARN_80)
        ->and(GoalAlert::evaluate(100000, 100000))->toBe(GoalAlert::REACHED_100)
        ->and(GoalAlert::evaluate(150000, 100000))->toBe(GoalAlert::REACHED_100);
});

it('sem meta definida não avisa', function () {
    expect(GoalAlert::evaluate(500000, 0))->toBe(GoalAlert::NONE);
});
