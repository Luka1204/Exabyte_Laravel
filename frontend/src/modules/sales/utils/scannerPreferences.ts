export type ScannerSuffix = 'Enter' | 'Tab' | 'manual';

const STORAGE_KEY = 'exabyte.pos.scanner-suffix';
const SUFFIXES: ScannerSuffix[] = ['Enter', 'Tab', 'manual'];

export function loadScannerSuffix(storage: Storage = localStorage): ScannerSuffix {
    const savedSuffix = storage.getItem(STORAGE_KEY);

    return SUFFIXES.find((suffix) => suffix === savedSuffix) ?? 'Enter';
}

export function saveScannerSuffix(
    suffix: ScannerSuffix,
    storage: Storage = localStorage,
): void {
    storage.setItem(STORAGE_KEY, suffix);
}
