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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', $this->user()?->id)],
            'from' => ['nullable', 'date_format:d-m-Y'],
            'to' => ['nullable', 'date_format:d-m-Y'],
            'sort' => ['nullable', 'string', Rule::in(['date', 'amount'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
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
                    $validator->errors()->add('from', 'The start date must be before or equal to the end date.');
                }
            },
        ];
    }
}

