<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait Indexable
{
    private array $data = [];

    private array $sorts = [];

    public function setSorts(array $sorts): void
    {
        $this->sorts = $sorts;
    }

    public function getData(): array
    {
        if (! empty($this->data)) {
            return $this->data;
        }

        $validated = $this->validate([
            'sort' => ['nullable', 'string', 'in:'.implode(',', $this->sorts)],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
        ]);

        $this->data = array_merge([
            'sort' => $this->sorts[0] ?? null,
            'direction' => 'desc',
            'per_page' => 15,
        ], $validated);

        if (isset($this->data['per_page'])) {
            $this->data['per_page'] = (int) $this->data['per_page'];
        }

        return $this->data;
    }

    public function getPage(): int
    {
        return $this->getData()['page'] ?? 1;
    }

    public function getPerPage(): int
    {
        return $this->getData()['per_page'] ?? 15;
    }

    public function getSort(): ?string
    {
        return $this->getData()['sort'] ?? $this->sorts[0] ?? null;
    }

    public function getDirection(): ?string
    {
        return $this->getData()['direction'] ?? 'desc';
    }

    public function getSorts(): array
    {
        return [
            'sort_by' => $this->getSort() ?? $this->sorts[0] ?? null,
            'sort_direction' => $this->getDirection() ?? 'desc',
        ];
    }
}
