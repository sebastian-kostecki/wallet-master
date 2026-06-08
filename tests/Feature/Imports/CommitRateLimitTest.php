<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Queue::fake();
});

test('import commit is rate limited to ten requests per minute', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $imports = collect(range(1, 11))->map(fn (int $i) => Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'draft',
        'mapping' => [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
        ],
    ]));

    foreach ($imports->take(10) as $import) {
        $this->actingAs($user)
            ->postJson(route('imports.commit', $import))
            ->assertAccepted();
    }

    $this->actingAs($user)
        ->postJson(route('imports.commit', $imports->last()))
        ->assertStatus(429);
});
