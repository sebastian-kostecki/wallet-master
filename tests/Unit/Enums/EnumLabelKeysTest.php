<?php

use App\Enums\AccountType;
use App\Enums\Bank;

it('returns stable account type label keys', function () {
    expect(AccountType::Checking->labelKey())->toBe('accounts.enums.accountType.checking');
    expect(AccountType::Savings->labelKey())->toBe('accounts.enums.accountType.savings');
});

it('returns stable bank label keys', function () {
    expect(Bank::BnpParibas->labelKey())->toBe('accounts.enums.bank.bnp-paribas');
    expect(Bank::MBank->labelKey())->toBe('accounts.enums.bank.mbank');
    expect(Bank::Cash->labelKey())->toBe('accounts.enums.bank.cash');
});
