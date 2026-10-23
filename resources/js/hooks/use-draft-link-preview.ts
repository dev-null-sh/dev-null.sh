import { useEffect, useState } from 'react';
import DraftLinkPreviewController from '@/actions/App/Http/Controllers/DraftLinkPreviewController';
import type { LinkPreview } from '@/types';

const PREVIEW_DELAY_MS = 400;

type PreviewResult = { url: string; preview: LinkPreview | null };
type PreviewResponse = { link_preview: LinkPreview | null };

async function fetchDraftLinkPreview(
    url: string,
    signal: AbortSignal,
): Promise<LinkPreview | null> {
    try {
        const response = await fetch(
            DraftLinkPreviewController.show.url({ query: { url } }),
            { headers: { Accept: 'application/json' }, signal },
        );

        if (!response.ok) {
            return null;
        }

        const data = (await response.json()) as PreviewResponse;

        return data.link_preview;
    } catch {
        return null;
    }
}

export function useDraftLinkPreview(url: string | null) {
    const [result, setResult] = useState<PreviewResult | null>(null);

    useEffect(() => {
        if (url === null) {
            return;
        }

        const controller = new AbortController();
        const load = async () => {
            const preview = await fetchDraftLinkPreview(url, controller.signal);

            if (controller.signal.aborted) {
                return;
            }

            setResult({ url, preview });
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
