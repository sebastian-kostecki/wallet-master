<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Support\Categories\CategoryForTransactionType;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesCategoryId
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function categoryIdRules(bool $required = true): array
    {
        $rules = [
            'integer',
            Rule::exists('categories', 'id')->where(
                fn ($query) => $query->where('user_id', $this->user()->id),
            ),
        ];

        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'sometimes');
        }

        return ['category_id' => $rules];
    }

    protected function validateCategoryMatchesAmount(Validator $validator, string $amountField = 'amount'): void
    {
        $validator->after(function (Validator $validator) use ($amountField): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $categoryId = $this->input('category_id');
            $amount = $this->input($amountField);

            if (! is_numeric($categoryId) || ! is_numeric($amount)) {
                return;
            }

            try {
                $transactionType = TransactionType::fromAmount(
                    TransactionDedupe::amountToDecimalString($amount),
                );
            } catch (\Throwable) {
                return;
            }

            $expectedCategoryType = CategoryForTransactionType::categoryTypeFor($transactionType);
            if ($expectedCategoryType === null) {
                return;
            }

            $category = Category::query()
                ->where('user_id', $this->user()->id)
                ->whereKey((int) $categoryId)
                ->first();

            if ($category === null) {
                return;
            }

            if ($category->type !== $expectedCategoryType) {
                $validator->errors()->add('category_id', 'Category type does not match transaction amount sign.');
            }
        });
    }

    protected function validateCategoryOwned(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('category_id')) {
                return;
            }

            $categoryId = $this->input('category_id');
            if (! is_numeric($categoryId)) {
                return;
            }

            $exists = Category::query()
                ->where('user_id', $this->user()->id)
                ->whereKey((int) $categoryId)
                ->exists();

            if (! $exists) {
                $validator->errors()->add('category_id', 'Invalid category.');
            }
        });
    }
}
