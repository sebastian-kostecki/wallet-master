<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\Bank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'bank' => ['required', Rule::enum(Bank::class)],
            'type' => ['required', Rule::enum(AccountType::class)],
            'opening_balance' => ['required', 'numeric'],
        ];
    }
}
