import { useEffect, useState } from 'react';
import DraftLinkPreviewController from '@/actions/App/Http/Controllers/DraftLinkPreviewController';
import type { LinkPreview } from '@/types';

const PREVIEW_DELAY_MS = 400;

type PreviewResult = { url: string; preview: LinkPreview | null };

export function useDraftLinkPreview(url: string | null) {
    const [result, setResult] = useState<PreviewResult | null>(null);

    useEffect(() => {
        if (url === null) {
            return;
        }

        const controller = new AbortController();
        const load = async () => {
            try {
                const response = await fetch(
                    DraftLinkPreviewController.show.url({ query: { url } }),
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );
                const data = response.ok
                    ? ((await response.json()) as {
                          link_preview: LinkPreview | null;
                      })
                    : { link_preview: null };

                if (!controller.signal.aborted) {
                    setResult({ url, preview: data.link_preview });
                }
            } catch {
                if (!controller.signal.aborted) {
                    setResult({ url, preview: null });
                }
            }
        };

        const timeout = window.setTimeout(() => void load(), PREVIEW_DELAY_MS);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [url]);

    return {
        preview: result?.url === url ? result.preview : null,
        isLoading: url !== null && result?.url !== url,
    };
}
