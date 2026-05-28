<?php

declare(strict_types=1);

namespace App\Data\Transactions;

final readonly class TransactionIndexFilters
{
    public function __construct(
        public ?int $accountId,
        public ?string $from,
        public ?string $to,
    ) {}

    /**
     * @param  array{account_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    public static function fromArray(array $filters): self
    {
        return new self(
            accountId: isset($filters['account_id']) ? (int) $filters['account_id'] : null,
            from: ! empty($filters['from']) ? (string) $filters['from'] : null,
            to: ! empty($filters['to']) ? (string) $filters['to'] : null,
        );
    }
}
