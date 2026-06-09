<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

test('import upload is rate limited to ten requests per minute', function () {
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

    $file = UploadedFile::fake()->createWithContent(
        'import.csv',
        "date;amount;description\n01-04-2026;-10,00;Coffee\n",
    );

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)->post(route('imports.upload'), [
            'account_id' => $account->id,
            'file' => $file,
        ])->assertCreated();
    }

    $this->actingAs($user)->post(route('imports.upload'), [
        'account_id' => $account->id,
        'file' => $file,
    ])->assertStatus(429);
});
