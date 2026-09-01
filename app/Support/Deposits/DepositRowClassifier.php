<?php

declare(strict_types=1);

namespace App\Support\Deposits;

use App\Imports\BankAdapters\BankImportAdapter;
use App\Imports\ParsedImportRow;
use App\Models\User;

final class DepositRowClassifier
{
    /**
     * @param  array<string, string>  $row
     * @param  array{date: string, amount: string, description: string, subject?: ?string}  $mapping
     */
    public function classify(
        BankImportAdapter $adapter,
        array $row,
        array $mapping,
        ParsedImportRow $parsed,
        User $user,
    ): DepositClassification {
        unset($user);

        return $adapter->classifyDepositRow($row, $mapping, $parsed);
    }
}
