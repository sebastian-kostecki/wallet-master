import { accountKindFromUrl, defaultBankForKind, isWalletAccount } from '@/lib/accountKind';
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
