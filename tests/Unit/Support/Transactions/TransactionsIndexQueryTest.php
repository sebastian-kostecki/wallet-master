<?php

use App\Support\Transactions\TransactionsIndexQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Route::get('/transactions', fn () => 'ok')->name('transactions.index');
});

test('remember stores only whitelisted non-empty query keys', function () {
    $request = Request::create('/transactions', 'GET', [
        'account_id' => 5,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
        'sort' => 'amount',
        'direction' => 'asc',
        'per_page' => 25,
        'page' => 3,
        'evil' => 'drop-me',
    ]);

    TransactionsIndexQuery::remember($request);

    expect(session(TransactionsIndexQuery::sessionKey()))->toBe([
        'account_id' => 5,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
        'sort' => 'amount',
        'direction' => 'asc',
        'per_page' => 25,
    ]);
});

test('remember stores cleared state with only sort and direction', function () {
    $request = Request::create('/transactions', 'GET', [
        'sort' => 'date',
        'direction' => 'desc',
    ]);

    TransactionsIndexQuery::remember($request);

    expect(session(TransactionsIndexQuery::sessionKey()))->toBe([
        'sort' => 'date',
        'direction' => 'desc',
    ]);
});

test('toQueryString builds query prefix or empty string', function () {
    session([TransactionsIndexQuery::sessionKey() => [
        'from' => '01-04-2026',
        'account_id' => 2,
    ]]);

    expect(TransactionsIndexQuery::toQueryString())->toBe('?from=01-04-2026&account_id=2');
});

test('redirect prefers request query over session', function () {
    session([TransactionsIndexQuery::sessionKey() => ['from' => '01-01-2026']]);

    $request = Request::create('/transactions?from=15-06-2026', 'POST');
    $response = TransactionsIndexQuery::redirect($request);

    expect($response->getTargetUrl())->toBe(route('transactions.index', ['from' => '15-06-2026']));
});

test('redirect falls back to session when request has no whitelisted keys', function () {
    session([TransactionsIndexQuery::sessionKey() => [
        'account_id' => 9,
        'sort' => 'date',
        'direction' => 'desc',
    ]]);

    $response = TransactionsIndexQuery::redirect(Request::create('/transactions', 'DELETE'));

    expect($response->getTargetUrl())->toBe(route('transactions.index', [
        'account_id' => 9,
        'sort' => 'date',
        'direction' => 'desc',
    ]));
});
