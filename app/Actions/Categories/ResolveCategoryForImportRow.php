<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Enums\Bank;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Integrations\DescriptionMemory\DescriptionMemoryRepository;
use App\Models\Category;
use App\Models\User;
use App\Telemetry\Event;

final class ResolveCategoryForImportRow
{
    public function __construct(
        private DescriptionMemoryRepository $descriptionMemory,
    ) {}

    public function handle(
        User $user,
        Bank $bank,
        string $rawStatementDescription,
        TransactionType $transactionType,
    ): int {
        $categoryType = $transactionType === TransactionType::Income
            ? CategoryType::Income
            : CategoryType::Expense;

        $suggested = $this->descriptionMemory->suggest(
            userId: (int) $user->id,
            bank: $bank,
            rawStatementDescription: $rawStatementDescription,
        );

        if ($suggested?->categoryId !== null) {
            $category = Category::query()
                ->where('user_id', $user->id)
                ->whereKey($suggested->categoryId)
                ->where('type', $categoryType)
                ->first();

            if ($category !== null) {
                Event::record('category_memory_hit', [
                    'category_id' => $category->id,
                    'bank' => $bank->value,
                ], $user->id);

                return $category->id;
            }
        }

        Event::record('category_memory_miss', [
            'bank' => $bank->value,
        ], $user->id);

        return $this->firstCategoryIdByType($user, $categoryType);
    }

    private function firstCategoryIdByType(User $user, CategoryType $type): int
    {
        $category = Category::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->ordered()
            ->first();

        if ($category === null) {
            app(EnsureUserCategories::class)->handle($user);

            $category = Category::query()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->ordered()
                ->firstOrFail();
        }

        return $category->id;
    }
}
