<?php

declare(strict_types=1);

namespace App\Http\Requests\Deposits;

use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConfirmDepositCandidateRequest extends FormRequest
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
        /** @var Transaction|null $transaction */
        $transaction = $this->route('transaction');

        return [
            'deposit_id' => [
                Rule::requiredIf(fn () => $transaction?->deposit_candidate_kind === 'close_principal'),
                'nullable',
                'integer',
                Rule::exists('deposits', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'matures_at' => ['nullable', 'date_format:d-m-Y'],
        ];
    }
}
