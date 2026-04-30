<?php

declare(strict_types=1);

namespace App\Http\Requests\Imports;

use App\Models\Import;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreImportCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Import $import */
        $import = $this->route('import');

        return $this->user()?->can('commit', $import) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Import $import */
        $import = $this->route('import');
        /** @var list<string> $headers */
        $headers = (array) data_get($import->details, 'headers', []);

        return [
            'mapping' => ['required', 'array'],
            'mapping.date' => ['required', 'string', Rule::in($headers)],
            'mapping.amount' => ['required', 'string', Rule::in($headers)],
            'mapping.description' => ['required', 'string', Rule::in($headers)],
            'mapping.subject' => ['nullable', 'string', Rule::in($headers)],
        ];
    }
}
