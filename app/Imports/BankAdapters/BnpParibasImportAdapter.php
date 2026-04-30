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
}
