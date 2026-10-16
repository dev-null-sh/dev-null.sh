export type StoredImages = {
    images: File[];
    descriptions: string[];
};

const databaseName = 'devnull';
const storeName = 'images';

let database: Promise<IDBDatabase | null> | undefined;

const open = (): Promise<IDBDatabase | null> => {
    database ??= new Promise((resolve) => {
        try {
            const request = window.indexedDB.open(databaseName, 1);

            request.onupgradeneeded = () =>
                request.result.createObjectStore(storeName);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => resolve(null);
        } catch {
            resolve(null);
        }
    });

    return database;
};

/**
 * Runs the operation in its own transaction, and tells whether it was stored,
 * so a full disk or a browser without IndexedDB only costs the images their persistence.
 */
const transact = async (
    mode: IDBTransactionMode,
    operate: (store: IDBObjectStore) => void,
): Promise<boolean> => {
    const connection = await open();

    return new Promise((resolve) => {
        if (connection === null) {
            resolve(false);

            return;
        }

        try {
            const transaction = connection.transaction(storeName, mode);

            transaction.oncomplete = () => resolve(true);
            transaction.onabort = () => resolve(false);
            operate(transaction.objectStore(storeName));
        } catch {
            resolve(false);
        }
    });
};

export async function readStoredImages(): Promise<Map<string, StoredImages>> {
    const stored = new Map<string, StoredImages>();

    await transact('readonly', (store) => {
        const request = store.openCursor();

        request.onsuccess = () => {
            const cursor = request.result;

            if (cursor === null) {
                return;
            }

            if (typeof cursor.key === 'string' && Array.isArray(cursor.value)) {
                const entries = cursor.value.flatMap((value: unknown) => {
                    if (value instanceof File) {
                        return [{ file: value, description: '' }];
                    }

                    if (
                        typeof value === 'object' &&
                        value !== null &&
                        'file' in value &&
                        value.file instanceof File
                    ) {
                        return [
                            {
                                file: value.file,
                                description:
                                    'description' in value &&
                                    typeof value.description === 'string'
                                        ? value.description
                                        : '',
                            },
                        ];
                    }

                    return [];
                });

                stored.set(cursor.key, {
                    images: entries.map((entry) => entry.file),
                    descriptions: entries.map((entry) => entry.description),
                });
            }

            cursor.continue();
        };
    });

    return stored;
}

export function writeStoredImages(
    key: string,
    images: File[],
    descriptions: string[],
): void {
    void transact('readwrite', (store) => {
        if (images.length === 0) {
            store.delete(key);
        } else {
            store.put(
                images.map((file, index) => ({
                    file,
                    description: descriptions[index] ?? '',
                })),
                key,
            );
        }
    });
}

export function deleteStoredImages(prefix: string): void {
    void transact('readwrite', (store) =>
        store.delete(IDBKeyRange.bound(prefix, `${prefix}￿`)),
    );
}
