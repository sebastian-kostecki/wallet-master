<?php

declare(strict_types=1);

namespace App\Data\Transactions;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class TransactionIndexResult
{
    /**
     * @param  LengthAwarePaginator<int, Transaction>  $paginator
     */
    public function __construct(
        public LengthAwarePaginator $paginator,
        public string|int|float $totalIncome,
        public string|int|float $totalExpense,
    ) {}
}
