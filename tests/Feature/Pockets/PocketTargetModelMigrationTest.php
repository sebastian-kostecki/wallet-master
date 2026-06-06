<?php

use App\Models\Pocket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('pockets target model migration migrates annual estimate into pocket fields', function () {
    Carbon::setTestNow('2026-06-04');

    try {
        if (Schema::hasTable('pockets') && ! Schema::hasTable('goals')) {
            Schema::rename('pockets', 'goals');
        }

        $user = User::factory()->create();
        $pocketId = DB::table('goals')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Poduszka finansowa',
            'icon' => 'target',
            'color' => '#6366f1',
            'sort_order' => 10,
            'currency_id' => DB::table('currencies')->where('code', 'PLN')->value('id'),
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_06_04_140000_refactor_goals_target_model.php');
        $migration->down();

        DB::table('goal_annual_estimates')->insert([
            'goal_id' => $pocketId,
            'year' => 2026,
            'amount' => '4800.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        if (Schema::hasTable('goals') && ! Schema::hasTable('pockets')) {
            Schema::rename('goals', 'pockets');
        }

        $pocket = Pocket::query()->find($pocketId);

        expect($pocket)->not->toBeNull();
        expect((string) $pocket->target_amount)->toBe('4800.00');
        expect($pocket->planning_mode?->value)->toBe('monthly');
        expect((string) $pocket->monthly_contribution)->toBe('400.00');
        expect(Schema::hasTable('goal_annual_estimates'))->toBeFalse();
        expect(Schema::hasTable('goal_monthly_estimates'))->toBeFalse();
    } finally {
        Carbon::setTestNow();
    }
});
