<?php

use App\Enums\PocketPlanningMode;
use App\Models\Pocket;
use App\Support\Pockets\PocketPlanningProjection;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('by_date mode recommends monthly from remaining amount and months left', function () {
    Carbon::setTestNow('2026-06-04');

    $pocket = new Pocket([
        'target_amount' => '5000.00',
        'planning_mode' => PocketPlanningMode::ByDate,
        'target_date' => '2026-10-31',
        'monthly_contribution' => null,
    ]);

    $recommended = PocketPlanningProjection::recommendedMonthly($pocket, '2000.00');

    // remaining 3000, months Jun–Oct inclusive = 5
    expect($recommended)->toBe('600.00');
});

test('monthly mode projects completion using effective savings rate', function () {
    Carbon::setTestNow('2026-06-30');

    $pocket = new Pocket([
        'target_amount' => '1200.00',
        'planning_mode' => PocketPlanningMode::Monthly,
        'monthly_contribution' => '200.00',
    ]);

    $monthlyNets = [
        '2026-04' => '100.00',
        '2026-05' => '200.00',
    ];

    $projected = PocketPlanningProjection::projectedCompletionDate($pocket, '300.00', $monthlyNets);

    // remaining 900, effective rate (100+200)/2 = 150, ceil(900/150)=6 months -> 2026-12-31
    expect($projected?->toDateString())->toBe('2026-12-31');
});
