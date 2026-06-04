<?php

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Goals\GoalBalance;
use Illuminate\Support\Str;

test('cumulative balance sums savings transfer legs across all months', function () {
    $user = User::factory()->create();
    $ror = Account::factory()->create(['user_id' => $user->id, 'type' => AccountType::Ror]);
    $savings = Account::factory()->create(['user_id' => $user->id, 'type' => AccountType::Savings]);
    $goal = Goal::factory()->create(['user_id' => $user->id]);
    $transferId = (string) Str::uuid();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $ror->id,
        'goal_id' => $goal->id,
        'transfer_id' => $transferId,
        'amount' => '-300.00',
        'booked_at' => '2026-01-15',
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'goal_id' => $goal->id,
        'transfer_id' => $transferId,
        'amount' => '300.00',
        'booked_at' => '2026-01-15',
    ]);

    $transferId2 = (string) Str::uuid();
    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'goal_id' => $goal->id,
        'transfer_id' => $transferId2,
        'amount' => '-100.00',
        'booked_at' => '2026-03-10',
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $ror->id,
        'goal_id' => $goal->id,
        'transfer_id' => $transferId2,
        'amount' => '100.00',
        'booked_at' => '2026-03-10',
    ]);

    $result = GoalBalance::cumulative($user, $goal);

    expect($result)->toBe([
        'saved_total' => '300.00',
        'released_total' => '100.00',
        'balance' => '200.00',
    ]);
});
