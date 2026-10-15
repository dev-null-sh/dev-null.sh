const storage = (): Storage | null => {
    try {
        return typeof window === 'undefined' ? null : window.localStorage;
    } catch {
        return null;
    }
};

export function readStorage(key: string): string {
    try {
        return storage()?.getItem(key) ?? '';
    } catch {
        return '';
    }
}

export function writeStorage(key: string, value: string): void {
    try {
        if (value === '') {
            storage()?.removeItem(key);
        } else {
            storage()?.setItem(key, value);
        }
    } catch {
        return;
    }
}

export function storedKeys(): string[] {
    try {
        const store = storage();

        return Array.from(
            { length: store?.length ?? 0 },
            (_, index) => store?.key(index) ?? '',
        );
    } catch {
        return [];
    }
}
