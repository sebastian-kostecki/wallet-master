export type AccountKind = 'accounts' | 'wallets';

export function accountKindFromUrl(url: string): AccountKind {
    const kind = new URLSearchParams(url.split('?')[1] ?? '').get('kind');

    return kind === 'wallets' ? 'wallets' : 'accounts';
}

export function isWalletAccount(account: { bank: string }): boolean {
    return account.bank === 'cash';
}

export function defaultBankForKind(kind: AccountKind, banks: Array<{ value: string }>): string {
    if (kind === 'wallets') {
        return 'cash';
    }

    return banks.find((bank) => bank.value !== 'cash')?.value ?? banks[0]?.value ?? '';
}
