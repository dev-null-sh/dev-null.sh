import type { ReloadOptions } from '@inertiajs/core';

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
