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

export async function readStoredImages(): Promise<Map<string, File[]>> {
    const stored = new Map<string, File[]>();

    await transact('readonly', (store) => {
        const request = store.openCursor();

        request.onsuccess = () => {
            const cursor = request.result;

            if (cursor === null) {
                return;
            }

            if (typeof cursor.key === 'string' && Array.isArray(cursor.value)) {
                stored.set(
                    cursor.key,
                    cursor.value.filter((file) => file instanceof File),
                );
            }

            cursor.continue();
        };
    });

    return stored;
}

export function writeStoredImages(key: string, images: File[]): void {
    void transact('readwrite', (store) => {
        if (images.length === 0) {
            store.delete(key);
        } else {
            store.put(images, key);
        }
    });
}

export function deleteStoredImages(prefix: string): void {
    void transact('readwrite', (store) =>
        store.delete(IDBKeyRange.bound(prefix, `${prefix}￿`)),
    );
}
