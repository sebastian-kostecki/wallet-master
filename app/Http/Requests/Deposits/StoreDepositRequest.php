<?php

declare(strict_types=1);

namespace App\Http\Requests\Deposits;

use App\Enums\Bank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $bankAccountRule = Rule::exists('accounts', 'id')
            ->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('user_id', $this->user()->id)
                ->where('bank', '!=', Bank::Cash->value)
            );

        return [
            'source_account_id' => ['required', 'integer', $bankAccountRule],
            'principal' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'opened_at' => ['required', 'date_format:d-m-Y'],
            'name' => ['nullable', 'string', 'max:255'],
            'matures_at' => ['nullable', 'date_format:d-m-Y', 'after_or_equal:opened_at'],
        ];
    }

    /**
     * @return array{
     *   source_account_id: int,
     *   principal: numeric-string|float|int,
     *   opened_at: string,
     *   name?: ?string,
     *   matures_at?: ?string,
     * }
     */
    public function validated($key = null, $default = null): array
    {
        return parent::validated($key, $default);
    }
}
