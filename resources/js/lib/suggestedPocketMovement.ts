export function suggestedPocketMovement(fromType: string | undefined, toType: string | undefined): 'deposit' | 'withdrawal' {
    const fromSav = fromType === 'savings';
    const toSav = toType === 'savings';

    if (fromSav !== toSav) {
        return toSav ? 'deposit' : 'withdrawal';
    }

    return 'deposit';
}
