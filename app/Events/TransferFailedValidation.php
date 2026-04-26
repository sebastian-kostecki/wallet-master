<?php

namespace App\Events;

final readonly class TransferFailedValidation
{
    /**
     * @param  array<int, string>  $fields
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        public ?int $userId,
        public array $fields,
        public array $errors,
    ) {}
}
