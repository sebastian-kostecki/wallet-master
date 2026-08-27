import { accountKindFromUrl, accountsPageKindFromUrl, accountsViewModeStorageKey, defaultBankForKind, isWalletAccount } from '@/lib/accountKind';
import { describe, expect, it } from 'vitest';

describe('accountKindFromUrl', () => {
    it('defaults to accounts when kind is missing or invalid', () => {
        expect(accountKindFromUrl('/konta')).toBe('accounts');
        expect(accountKindFromUrl('/konta?kind=accounts')).toBe('accounts');
        expect(accountKindFromUrl('/konta?kind=nope')).toBe('accounts');
    });

    it('returns wallets only for kind=wallets', () => {
        expect(accountKindFromUrl('/konta?kind=wallets')).toBe('wallets');
    });

    it('stays accounts for kind=deposits so account forms keep bank prefill', () => {
        expect(accountKindFromUrl('/konta?kind=deposits')).toBe('accounts');
    });
});

describe('accountsPageKindFromUrl', () => {
    it('defaults to accounts when kind is missing or invalid', () => {
        expect(accountsPageKindFromUrl('/konta')).toBe('accounts');
        expect(accountsPageKindFromUrl('/konta?kind=accounts')).toBe('accounts');
        expect(accountsPageKindFromUrl('/konta?kind=nope')).toBe('accounts');
    });

    it('returns wallets only for kind=wallets', () => {
        expect(accountsPageKindFromUrl('/konta?kind=wallets')).toBe('wallets');
    });

    it('returns deposits only for kind=deposits', () => {
        expect(accountsPageKindFromUrl('/konta?kind=deposits')).toBe('deposits');
    });
});

describe('accountsViewModeStorageKey', () => {
    it('uses deposits key only for the deposits tab', () => {
        expect(accountsViewModeStorageKey('deposits')).toBe('deposits.viewMode');
    });

    it('uses accounts key for accounts and wallets tabs', () => {
        expect(accountsViewModeStorageKey('accounts')).toBe('accounts.viewMode');
        expect(accountsViewModeStorageKey('wallets')).toBe('accounts.viewMode');
    });
});

describe('isWalletAccount', () => {
    it('treats cash as a wallet and every other bank as a rachunek', () => {
        expect(isWalletAccount({ bank: 'cash' })).toBe(true);
        expect(isWalletAccount({ bank: 'mbank' })).toBe(false);
        expect(isWalletAccount({ bank: 'bnp-paribas' })).toBe(false);
    });
});

describe('defaultBankForKind', () => {
    const banks = [{ value: 'bnp-paribas' }, { value: 'mbank' }, { value: 'cash' }];

    it('prefills cash for wallets', () => {
        expect(defaultBankForKind('wallets', banks)).toBe('cash');
    });

    it('prefills the first non-cash bank for accounts', () => {
        expect(defaultBankForKind('accounts', banks)).toBe('bnp-paribas');
    });
});
