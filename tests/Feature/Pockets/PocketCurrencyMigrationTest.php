<?php

use App\Models\Currency;
use App\Models\Pocket;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('pockets currency migration backfills existing pockets with PLN', function () {
    $legacyTable = 'g'.'oals';
    $migrationPath = database_path('migrations/2026_06_05_100000_add_currency_id_to_'.'g'.'oals'.'_table.php');

    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    if (Schema::hasTable('pockets') && ! Schema::hasTable($legacyTable)) {
        Schema::rename('pockets', $legacyTable);
    }

    Schema::table($legacyTable, function ($table) {
        $table->dropForeign(['currency_id']);
        $table->dropColumn('currency_id');
    });

    $pocketId = DB::table($legacyTable)->insertGetId([
        'user_id' => $user->id,
        'name' => 'Poduszka finansowa',
        'icon' => 'target',
        'color' => '#6366f1',
        'sort_order' => 10,
        'is_archived' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require $migrationPath;
    $migration->up();

    if (Schema::hasTable($legacyTable) && ! Schema::hasTable('pockets')) {
        Schema::rename($legacyTable, 'pockets');
    }

    $pocket = Pocket::query()->find($pocketId);

    expect($pocket)->not->toBeNull();
    expect((int) $pocket->currency_id)->toBe($plnId);
    expect(Schema::hasColumn('pockets', 'currency_id'))->toBeTrue();
});
