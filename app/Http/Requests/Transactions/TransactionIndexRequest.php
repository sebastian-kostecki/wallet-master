<?php

namespace App\Http\Requests\Transactions;

use App\Http\Requests\Concerns\Indexable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransactionIndexRequest extends FormRequest
{
    use Indexable;

    /** @var array{account_id: ?int, from: ?string, to: ?string}|null */
    private ?array $filters = null;

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
            'from.before_or_equal' => 'Data „Od” nie może być późniejsza niż „Do”.',
            'to.after_or_equal' => 'Data „Do” nie może być wcześniejsza niż „Od”.',
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accountExistsRule = Rule::exists('accounts', 'id')
            ->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('user_id', $this->user()->id)
            );

        return [
            'account_id' => [
                'nullable',
                'integer',
                $accountExistsRule,
            ],
            'from' => ['nullable', 'date_format:d-m-Y', 'before_or_equal:to'],
            'to' => ['nullable', 'date_format:d-m-Y', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array{account_id: ?int, from: ?string, to: ?string}
     */
    public function getFilters(): array
    {
        if ($this->filters !== null) {
            return $this->filters;
        }

        $validated = $this->validated();

        $accountId = isset($validated['account_id']) ? (int) $validated['account_id'] : null;
        $fromInput = isset($validated['from']) ? (string) $validated['from'] : null;
        $toInput = isset($validated['to']) ? (string) $validated['to'] : null;

        return $this->filters = [
            'account_id' => $accountId,
            'from' => $fromInput,
            'to' => $toInput,
        ];
    }

    public function getAccountId(): ?int
    {
        return $this->getFilters()['account_id'] ?? null;
    }

    public function getFrom(): ?string
    {
        return $this->getFilters()['from'] ?? null;
    }

    public function getTo(): ?string
    {
        return $this->getFilters()['to'] ?? null;
    }
}
