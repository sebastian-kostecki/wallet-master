<?php

declare(strict_types=1);

use App\Actions\Categories\EnsureUserCategories;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('currency_id')->constrained();
        });

        $ensure = app(EnsureUserCategories::class);

        User::query()->orderBy('id')->each(function (User $user) use ($ensure): void {
            $ensure->handle($user);

            $firstExpenseId = Category::query()
                ->where('user_id', $user->id)
                ->where('type', CategoryType::Expense)
                ->ordered()
                ->value('id');

            $firstIncomeId = Category::query()
                ->where('user_id', $user->id)
                ->where('type', CategoryType::Income)
                ->ordered()
                ->value('id');

            $savingsId = Category::query()
                ->where('user_id', $user->id)
                ->where('is_system', true)
                ->where('name', 'Oszczędności')
                ->value('id') ?? $firstExpenseId;

            Transaction::query()
                ->where('user_id', $user->id)
                ->whereNull('category_id')
                ->orderBy('id')
                ->each(function (Transaction $transaction) use ($firstExpenseId, $firstIncomeId, $savingsId): void {
                    $categoryId = match ($transaction->type) {
                        TransactionType::Income => $firstIncomeId,
                        TransactionType::Expense => $firstExpenseId,
                        TransactionType::Transfer => $savingsId,
                        TransactionType::Adjustment => $firstExpenseId,
                    };

                    $transaction->update(['category_id' => $categoryId]);
                });
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['category_id', 'booked_at']);
            $table->index(['user_id', 'category_id', 'booked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'booked_at']);
            $table->dropIndex(['user_id', 'category_id', 'booked_at']);
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
