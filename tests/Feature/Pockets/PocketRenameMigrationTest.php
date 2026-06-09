<?php

use App\Models\Pocket;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(fn () => $this->seed(CurrencySeeder::class));

test('fresh schema uses pockets and nullable category_id on transactions', function () {
    expect(Schema::hasTable('pockets'))->toBeTrue();
    expect(Schema::hasTable('goals'))->toBeFalse();
    expect(Schema::hasColumn('transactions', 'pocket_id'))->toBeTrue();
    expect(Schema::hasColumn('transactions', 'category_id'))->toBeTrue();
});

test('existing rows survive as pockets', function () {
    $user = User::factory()->create();
    Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    expect(Pocket::query()->where('name', 'Wakacje')->exists())->toBeTrue();
});
