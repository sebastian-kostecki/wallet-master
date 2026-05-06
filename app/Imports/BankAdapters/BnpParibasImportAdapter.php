<?php

declare(strict_types=1);

namespace App\Imports\BankAdapters;

use App\Enums\Bank;

final class BnpParibasImportAdapter extends AbstractBankImportAdapter
{
    public function bank(): Bank
    {
        return Bank::BnpParibas;
    }

    public function defaultMapping(array $headers): ?array
    {
        $fallback = parent::defaultMapping($headers);
        if ($fallback !== null) {
            return $fallback;
        }

        $date = $this->findHeader($headers, 'Data transakcji');
        $amount = $this->findHeader($headers, 'Kwota');
        $description = $this->findHeader($headers, 'Opis');

        if ($date === null || $amount === null || $description === null) {
            return null;
        }

        return [
            'date' => $date,
            'amount' => $amount,
            'description' => $description,
        ];
    }
}
