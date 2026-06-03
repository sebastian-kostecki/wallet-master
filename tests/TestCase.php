<?php

namespace Tests;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Categories\DefaultCategoryId;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Transaction::creating(function (Transaction $transaction): void {
            if ($transaction->category_id !== null || $transaction->user_id === null) {
                return;
            }

            $user = User::query()->find($transaction->user_id);
            if ($user === null) {
                return;
            }

            $type = $transaction->type;
            if (! $type instanceof TransactionType) {
                $type = TransactionType::tryFrom((string) $type) ?? TransactionType::Expense;
            }

            $transaction->category_id = DefaultCategoryId::for($user, $type);
        });
    }
}
