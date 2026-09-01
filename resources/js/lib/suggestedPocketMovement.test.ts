import { suggestedPocketMovement } from '@/lib/suggestedPocketMovement';
import { describe, expect, it } from 'vitest';

describe('suggestedPocketMovement', () => {
    it('returns deposit when destination is savings', () => {
        expect(suggestedPocketMovement('checking', 'savings')).toBe('deposit');
    });

    it('returns withdrawal when source is savings', () => {
        expect(suggestedPocketMovement('savings', 'checking')).toBe('withdrawal');
    });

    it('returns deposit when both or neither are savings', () => {
        expect(suggestedPocketMovement('checking', 'checking')).toBe('deposit');
        expect(suggestedPocketMovement('savings', 'savings')).toBe('deposit');
        expect(suggestedPocketMovement(undefined, undefined)).toBe('deposit');
    });
});
