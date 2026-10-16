export type ConvertedGif = { file: File; poster?: File };

let converting = false;

/** The module worker and its muxer/decoder are downloaded only when a GIF is attached. */
export function convertGif(
    file: File,
    signal: AbortSignal,
    progress: (message: string) => void,
): Promise<ConvertedGif> {
    if (converting) {
        return Promise.reject(
            new Error(
                'Another GIF is converting. Wait for it to finish, then attach this GIF again.',
            ),
        );
    }
    return new Promise((resolve, reject) => {
        const worker = new Worker(new URL('./worker.ts', import.meta.url), {
            type: 'module',
        });
        converting = true;
        const cleanup = () => {
            worker.terminate();
            converting = false;
            clearTimeout(timeout);
            signal.removeEventListener('abort', abort);
        };
        const abort = () => {
            cleanup();
            reject(new DOMException('Conversion cancelled', 'AbortError'));
        };
        const timeout = setTimeout(() => {
            cleanup();
            reject(
                new Error(
                    'GIF conversion took too long. Try a smaller animation.',
                ),
            );
        }, 120_000);
        signal.addEventListener('abort', abort, { once: true });
        if (signal.aborted) {
            abort();
            return;
        }
        worker.onerror = (event) => {
            cleanup();
            reject(new Error(`GIF conversion worker failed: ${event.message}`));
        };
        worker.onmessage = ({ data }) => {
            if (data.progress) {
                progress(data.progress);
                return;
            }
            cleanup();
            if (data.error) {
                reject(new Error(data.error));
                return;
            }
            if (data.static) {
                resolve({ file });
                return;
            }
            resolve({
                file: new File(
                    [data.video],
                    file.name.replace(/\.[^.]+$/, '') + '.webm',
                    { type: 'video/webm' },
                ),
                poster: new File([data.poster], 'poster.png', {
                    type: 'image/png',
                }),
            });
        };
        worker.postMessage(file);
    });
}
