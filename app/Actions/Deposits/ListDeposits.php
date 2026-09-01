<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\Bank;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ListDeposits
{
    /** @var Collection<int, Deposit> */
    private Collection $deposits;

    /** @var Collection<int, Account> */
    private Collection $bankAccounts;

    /** @var Collection<int, Category> */
    private Collection $incomeCategories;

    public function handle(User $user): void
    {
        $this->deposits = Deposit::query()
            ->whereBelongsTo($user)
            ->with(['currency', 'movements'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('opened_at')
            ->get();

        $this->bankAccounts = Account::query()
            ->whereBelongsTo($user)
            ->where('bank', '!=', Bank::Cash)
            ->whereNull('deleted_at')
            ->with('currency')
            ->orderBy('name')
            ->get();

        $this->incomeCategories = Category::query()
            ->whereBelongsTo($user)
            ->where('type', CategoryType::Income)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, Deposit>
     */
    public function getDeposits(): Collection
    {
        return $this->deposits;
    }

    /**
     * @return Collection<int, Account>
     */
    public function getBankAccounts(): Collection
    {
        return $this->bankAccounts;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getIncomeCategories(): Collection
    {
        return $this->incomeCategories;
    }
}
