<?php

use App\Models\Pocket;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('rename migration uses pockets table and pocket_id on transactions', function () {
    expect(Schema::hasTable('pockets'))->toBeTrue();
    expect(Schema::hasTable('goals'))->toBeFalse();
    expect(Schema::hasColumn('transactions', 'pocket_id'))->toBeTrue();
    expect(Schema::hasColumn('transactions', 'goal_id'))->toBeFalse();
});

test('existing goal rows survive rename as pockets', function () {
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    expect(Pocket::query()->where('name', 'Wakacje')->exists())->toBeTrue();
});
