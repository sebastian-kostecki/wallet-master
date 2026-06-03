export function normalizeAmount(input: string): string {
    return input.replace(/\s/g, '').replace(',', '.');
}

export function displayAmount(input: string): string {
    return input.replace('.', ',');
}
