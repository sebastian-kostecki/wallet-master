<?php

use App\Models\Currency;
use App\Models\Goal;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('goals currency migration backfills existing goals with PLN', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    Schema::table('goals', function ($table) {
        $table->dropForeign(['currency_id']);
        $table->dropColumn('currency_id');
    });

    $goalId = DB::table('goals')->insertGetId([
        'user_id' => $user->id,
        'name' => 'Poduszka finansowa',
        'icon' => 'target',
        'color' => '#6366f1',
        'sort_order' => 10,
        'is_archived' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_06_05_100000_add_currency_id_to_goals_table.php');
    $migration->up();

    $goal = Goal::query()->find($goalId);

    expect($goal)->not->toBeNull();
    expect((int) $goal->currency_id)->toBe($plnId);
    expect(Schema::hasColumn('goals', 'currency_id'))->toBeTrue();
});
