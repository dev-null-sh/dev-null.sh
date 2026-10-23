import { Link2 } from 'lucide-react';
import DraftLinkPreviewController from '@/actions/App/Http/Controllers/DraftLinkPreviewController';
import { GifEmbed } from '@/components/gif-embed';
import { LinkPreview } from '@/components/link-preview';
import { useDraftLinkPreview } from '@/hooks/use-draft-link-preview';
import { isGifUrl } from '@/lib/gifs';
import { hostname } from '@/lib/urls';
import type { LinkPreview as LinkPreviewData } from '@/types';

function DraftLinkPreviewFallback({
    url,
    isLoading,
}: {
    url: string;
    isLoading: boolean;
}) {
    return (
        <div className="mt-3 flex items-center gap-3 rounded-lg border border-dashed px-3 py-2">
            <Link2
                aria-hidden
                className="size-4 shrink-0 text-muted-foreground"
            />
            <div className="min-w-0">
                <p className="truncate font-mono text-[13px]">
                    {url.replace(/^https?:\/\//i, '')}
                </p>
                <p
                    className="text-sm leading-snug text-muted-foreground"
                    role="status"
                >
                    {isLoading
                        ? 'Loading link preview…'
                        : 'Link preview unavailable'}
                </p>
            </div>
        </div>
    );
}

export function DraftLinkPreview({ url }: { url: string | null }) {
    const { preview, isLoading } = useDraftLinkPreview(url);

    if (url === null) {
        return null;
    }

    return (
        <div data-test="link-card-preview" aria-busy={isLoading}>
            <DraftLinkPreviewContent
                url={url}
                preview={preview}
                isLoading={isLoading}
            />
        </div>
    );
}

function DraftLinkPreviewContent({
    url,
    preview,
    isLoading,
}: {
    url: string;
    preview: LinkPreviewData | null;
    isLoading: boolean;
}) {
    if (preview) {
        return (
            <LinkPreview
                key={preview.url}
                preview={preview}
                refreshUrl={DraftLinkPreviewController.show.url({
                    query: { url },
                })}
                emptyState={
                    <DraftLinkPreviewFallback url={url} isLoading={false} />
                }
            />
        );
    }

    if (isGifUrl(url) || hostname(url) === 'giphy.com') {
        return (
            <GifEmbed
                preview={{
                    url,
                    title: null,
                    description: null,
                    image_url: null,
                    site_name: null,
                    is_pending: isLoading,
                }}
            />
        );
    }

    return <DraftLinkPreviewFallback url={url} isLoading={isLoading} />;
}
