<?php

use App\Models\Goal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('goals target model migration migrates annual estimate into goal fields', function () {
    Carbon::setTestNow('2026-06-04');

    try {
        $user = User::factory()->create();
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Poduszka finansowa',
        ]);

        $migration = require database_path('migrations/2026_06_04_140000_refactor_goals_target_model.php');
        $migration->down();

        DB::table('goal_annual_estimates')->insert([
            'goal_id' => $goal->id,
            'year' => 2026,
            'amount' => '4800.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $goal->refresh();

        expect((string) $goal->target_amount)->toBe('4800.00');
        expect($goal->planning_mode?->value)->toBe('monthly');
        expect((string) $goal->monthly_contribution)->toBe('400.00');
        expect(Schema::hasTable('goal_annual_estimates'))->toBeFalse();
        expect(Schema::hasTable('goal_monthly_estimates'))->toBeFalse();
    } finally {
        Carbon::setTestNow();
    }
});
