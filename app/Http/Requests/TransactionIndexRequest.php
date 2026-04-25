<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class TransactionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.date_format' => 'Podaj datę w formacie DD-MM-YYYY.',
            'to.date_format' => 'Podaj datę w formacie DD-MM-YYYY.',
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        $accountExistsRule = Rule::exists('accounts', 'id')->whereNull('deleted_at');

        $accountExistsRule->where('user_id', $userId ?? 0);

        return [
            'account_id' => [
                'nullable',
                'integer',
                $accountExistsRule,
            ],
            'from' => ['nullable', 'date_format:d-m-Y'],
            'to' => ['nullable', 'date_format:d-m-Y'],
            'sort' => ['nullable', 'string', 'in:date,amount'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->fails()) {
                    return;
                }

                $from = $this->input('from');
                $to = $this->input('to');

                if (! is_string($from) || ! is_string($to) || $from === '' || $to === '') {
                    return;
                }

                $fromDate = CarbonImmutable::createFromFormat('d-m-Y', $from);
                $toDate = CarbonImmutable::createFromFormat('d-m-Y', $to);

                if ($fromDate->greaterThan($toDate)) {
                    $validator->errors()->add('from', 'Data „Od” nie może być późniejsza niż „Do”.');
                }
            },
        ];
    }
}
