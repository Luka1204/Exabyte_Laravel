import { beforeEach, describe, expect, it } from 'vitest';
import {
    loadScannerSuffix,
    saveScannerSuffix,
    type ScannerSuffix,
} from './scannerPreferences';

const STORAGE_KEY = 'exabyte.pos.scanner-suffix';

describe('scanner preferences', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('defaults to Enter when no preference has been saved', () => {
        expect(loadScannerSuffix()).toBe('Enter');
    });

    it.each<ScannerSuffix>(['Enter', 'Tab', 'manual'])(
        'persists and restores the %s suffix',
        (suffix) => {
            saveScannerSuffix(suffix);

            expect(localStorage.getItem(STORAGE_KEY)).toBe(suffix);
            expect(loadScannerSuffix()).toBe(suffix);
        },
    );

    it('defaults to Enter when stored data is not a supported suffix', () => {
        localStorage.setItem(STORAGE_KEY, 'Space');

        expect(loadScannerSuffix()).toBe('Enter');
    });

    it('surfaces storage failures to the caller', () => {
        const storage = {
            getItem: () => null,
            setItem: () => {
                throw new DOMException('Storage unavailable', 'QuotaExceededError');
            },
        } as unknown as Storage;

        expect(() => saveScannerSuffix('Tab', storage)).toThrow('Storage unavailable');
    });
});
