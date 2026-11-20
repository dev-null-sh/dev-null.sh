import type { ReloadOptions } from '@inertiajs/core';
import { router } from '@inertiajs/react';

export function backgroundPollOptions(
    requestOptions: ReloadOptions | (() => ReloadOptions),
): () => ReloadOptions {
    return () => {
        const options =
            typeof requestOptions === 'function'
                ? requestOptions()
                : requestOptions;

        return {
            ...options,
            onHttpException: (response) => {
                const result = options.onHttpException?.(response);

                // Deployment gateways may answer before Laravel can return an Inertia page.
                return [502, 503, 504, 520].includes(response.status)
                    ? false
                    : result;
            },
            onNetworkError: (error) => {
                options.onNetworkError?.(error);

                return false;
            },
        };
    };
}

/**
 * A background reload that settles once its request does, whether it succeeded, failed or was cancelled, so
 * whoever waits on it never starts another one while it is still on its way. Aborting cancels it.
 */
export function reloadInBackground(
    options: ReloadOptions,
    signal?: AbortSignal,
): Promise<void> {
    return new Promise((resolve) => {
        if (signal?.aborted) {
            resolve();

            return;
        }

        let cancel = () => {};
        const onAbort = () => cancel();

        signal?.addEventListener('abort', onAbort, { once: true });
        router.reload(
            backgroundPollOptions({
                ...options,
                onCancelToken: (token) => {
                    cancel = () => token.cancel();
                    options.onCancelToken?.(token);
                },
                onFinish: (visit) => {
                    signal?.removeEventListener('abort', onAbort);
                    options.onFinish?.(visit);
                    resolve();
                },
            })(),
        );
    });
}
