<?php

namespace App\Events;

final readonly class TransferCreated
{
    public function __construct(
        public int $userId,
        public string $transferId,
        public int $fromAccountId,
        public int $toAccountId,
        public string $amount,
        public string $date,
    ) {}
}
