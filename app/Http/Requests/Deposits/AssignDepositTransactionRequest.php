<?php

declare(strict_types=1);

namespace App\Http\Requests\Deposits;

use App\Enums\DepositMovementKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignDepositTransactionRequest extends FormRequest
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
        return [
            'kind' => ['required', Rule::enum(DepositMovementKind::class)],
            'deposit_id' => [
                Rule::requiredUnless(fn () => $this->input('kind') === DepositMovementKind::Open->value),
                'nullable',
                'integer',
                Rule::exists('deposits', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'matures_at' => ['nullable', 'date_format:d-m-Y'],
        ];
    }

    /**
     * @return array{
     *   kind: 'open'|'close_principal'|'interest',
     *   deposit_id?: int|null,
     *   name?: ?string,
     *   matures_at?: ?string,
     * }
     */
    public function validated($key = null, $default = null): array
    {
        return parent::validated($key, $default);
    }
}
