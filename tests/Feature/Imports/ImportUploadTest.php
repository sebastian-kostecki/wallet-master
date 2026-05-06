<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

test('user can upload import fixture file for own account', function (string $bankValue, string $fixturePath, array $expectedMapping) {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::from($bankValue),
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $content = file_get_contents(base_path($fixturePath));
    expect($content)->not->toBeFalse();

    $file = UploadedFile::fake()->createWithContent(basename($fixturePath), (string) $content);

    $response = $this
        ->actingAs($user)
        ->post(route('imports.upload'), [
            'account_id' => $account->id,
            'file' => $file,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('status', 'draft');
    $headers = $response->json('headers');
    expect($headers)->toBeArray()->not->toBeEmpty();

    $import = Import::query()->first();
    expect($import)->not->toBeNull();
    expect($import->status)->toBe('draft');
    expect(data_get($import->details, 'source_file'))->not->toBeNull();
    expect($import->mapping)->toMatchArray($expectedMapping);
})->with([
    [
        Bank::MBank->value,
        'tests/Fixtures/import/mbank-basic.csv',
        [
            'date' => 'Data operacji',
            'amount' => 'Kwota',
            'description' => 'Opis operacji',
            'subject' => 'Kategoria',
        ],
    ],
    [
        Bank::BnpParibas->value,
        'tests/Fixtures/import/bnp-basic.csv',
        [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
            'subject' => 'subject',
        ],
    ],
]);

test('user can upload xlsx import for own account', function () {
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

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['date', 'amount', 'description'],
        ['24-04-2026', '-12.34', 'Coffee'],
    ]);

    $path = sys_get_temp_dir().'/import-test-'.uniqid('', true).'.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    $content = file_get_contents($path);
    unlink($path);
    expect($content)->not->toBeFalse();

    $file = UploadedFile::fake()->createWithContent('import.xlsx', (string) $content);

    $response = $this
        ->actingAs($user)
        ->post(route('imports.upload'), [
            'account_id' => $account->id,
            'file' => $file,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('status', 'draft');
    $headers = $response->json('headers');
    expect($headers)->toBeArray()->not->toBeEmpty();
    expect($headers)->toContain('date', 'amount', 'description');

    $import = Import::query()->first();
    expect($import)->not->toBeNull();
    expect($import->status)->toBe('draft');
    expect(data_get($import->details, 'source_file'))->not->toBeNull();
    expect($import->mapping)->toMatchArray([
        'date' => 'date',
        'amount' => 'amount',
        'description' => 'description',
    ]);
});

test('user cannot upload import for deleted account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Deleted',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);
    $account->delete();

    $file = UploadedFile::fake()->createWithContent('import.csv', "date;amount;description\n24-04-2026;-12.34;Coffee");

    $this
        ->actingAs($user)
        ->post(route('imports.upload'), [
            'account_id' => $account->id,
            'file' => $file,
        ])
        ->assertSessionHasErrors('account_id');
});
