<?php

declare(strict_types=1);

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Transaction::query()
            ->where(function ($query): void {
                $query
                    ->where('type', TransactionType::Transfer)
                    ->orWhereNotNull('transfer_id');
            })
            ->update(['category_id' => null]);

        Category::query()
            ->where('is_system', true)
            ->where('name', 'Oszczędności')
            ->orderBy('id')
            ->each(function (Category $savingsCategory): void {
                $userId = $savingsCategory->user_id;

                $firstExpenseId = Category::query()
                    ->where('user_id', $userId)
                    ->where('type', CategoryType::Expense)
                    ->whereKeyNot($savingsCategory->id)
                    ->ordered()
                    ->value('id');

                $firstIncomeId = Category::query()
                    ->where('user_id', $userId)
                    ->where('type', CategoryType::Income)
                    ->ordered()
                    ->value('id');

                if ($firstExpenseId === null || $firstIncomeId === null) {
                    return;
                }

                Transaction::query()
                    ->where('category_id', $savingsCategory->id)
                    ->whereNull('transfer_id')
                    ->where('type', '!=', TransactionType::Transfer)
                    ->where('amount', '<', 0)
                    ->update(['category_id' => $firstExpenseId]);

                Transaction::query()
                    ->where('category_id', $savingsCategory->id)
                    ->whereNull('transfer_id')
                    ->where('type', '!=', TransactionType::Transfer)
                    ->where('amount', '>=', 0)
                    ->update(['category_id' => $firstIncomeId]);

                CategoryMonthlyEstimate::query()->where('category_id', $savingsCategory->id)->delete();
                CategoryAnnualEstimate::query()->where('category_id', $savingsCategory->id)->delete();
                $savingsCategory->delete();
            });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->unsignedBigInteger('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
        });
    }
};
