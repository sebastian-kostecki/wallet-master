<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Actions\Categories\EnsureUserCategories;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * @return list<array{level: string, message: string, context: array<string, mixed>}>
 */
function captureTelemetryLogs(callable $callback): array
{
    $captured = [];
    $state = new stdClass;
    $state->active = true;

    Event::listen(MessageLogged::class, function (MessageLogged $logged) use (&$captured, $state): void {
        if (! $state->active || ! isset($logged->context['event'])) {
            return;
        }

        $captured[] = [
            'level' => $logged->level,
            'message' => $logged->message,
            'context' => $logged->context,
        ];
    });

    try {
        $callback();
    } finally {
        $state->active = false;
    }

    return $captured;
}

/**
 * @param  list<array{level: string, message: string, context: array<string, mixed>}>  $logged
 */
function assertTelemetryEvent(array $logged, string $event, ?callable $contextMatcher = null): void
{
    $matched = false;

    foreach ($logged as $entry) {
        if ($entry['message'] !== $event) {
            continue;
        }

        if ($contextMatcher !== null && ! $contextMatcher($entry['context'])) {
            continue;
        }

        $matched = true;

        break;
    }

    expect($matched)->toBeTrue("Expected telemetry event [{$event}] to be logged.");
}

function ensureUserCategories(User $user): void
{
    app(EnsureUserCategories::class)->handle($user);
}

function defaultCategoryId(User $user, CategoryType $type = CategoryType::Expense): int
{
    ensureUserCategories($user);

    return (int) Category::query()
        ->where('user_id', $user->id)
        ->where('type', $type)
        ->ordered()
        ->value('id');
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function transactionWithCategory(User $user, array $attributes): array
{
    ensureUserCategories($user);

    if (! array_key_exists('category_id', $attributes)) {
        $type = $attributes['type'] ?? TransactionType::Expense;
        $categoryType = ($type === TransactionType::Income || $type === 'income')
            ? CategoryType::Income
            : CategoryType::Expense;
        $attributes['category_id'] = defaultCategoryId($user, $categoryType);
    }

    return $attributes;
}

function createImportWithFile(User $user, Account $account, string $content): Import
{
    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'queued',
        'mapping' => [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
            'subject' => 'subject',
        ],
        'details' => [
            'source_file' => "imports/{$user->id}/source-{$account->id}-".uniqid('', true).'.csv',
            'headers' => ['date', 'amount', 'description', 'subject'],
        ],
    ]);

    Storage::disk('local')->put((string) data_get($import->details, 'source_file'), $content);

    return $import;
}

/**
 * @param  array<string, string>  $mapping
 * @param  list<string>  $headers
 */
function createImportWithFixture(User $user, Account $account, string $fixturePath, array $mapping, array $headers): Import
{
    $extension = pathinfo($fixturePath, PATHINFO_EXTENSION);
    $sourceFile = "imports/{$user->id}/source-{$account->id}-".uniqid('', true).".{$extension}";
    $content = file_get_contents(base_path($fixturePath));
    expect($content)->not->toBeFalse();

    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'queued',
        'mapping' => $mapping,
        'details' => [
            'source_file' => $sourceFile,
            'headers' => $headers,
        ],
    ]);

    Storage::disk('local')->put($sourceFile, (string) $content);

    return $import;
}

function createTransferMatcherImport(User $user, Account $account, string $content): Import
{
    $sourceFile = "imports/{$user->id}/transfer-matcher-{$account->id}-".uniqid('', true).'.csv';

    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'queued',
        'mapping' => [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
            'subject' => 'subject',
        ],
        'details' => [
            'source_file' => $sourceFile,
            'headers' => ['date', 'amount', 'description', 'subject'],
        ],
    ]);

    Storage::disk('local')->put($sourceFile, $content);

    return $import;
}
