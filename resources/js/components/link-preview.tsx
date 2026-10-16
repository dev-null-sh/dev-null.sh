import { useState } from 'react';
import { GitHubLinkPreview } from '@/components/github-link-preview';
import { LinkEmbed } from '@/components/link-embed';
import { hostname } from '@/lib/urls';
import type { LinkPreview as LinkPreviewData } from '@/types';

export function LinkPreview({ preview }: { preview: LinkPreviewData }) {
    const [isImageBroken, setIsImageBroken] = useState(false);
    const imageUrl = isImageBroken ? null : preview.image_url;

    if (preview.embed) {
        return <LinkEmbed preview={preview} embed={preview.embed} />;
    }

    if (preview.github) {
        return (
            <GitHubLinkPreview
                url={preview.url}
                title={preview.title}
                github={preview.github}
            />
        );
    }

    if (!preview.title) {
        return null;
    }

    return (
        <a
            href={preview.url}
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            onClick={(event) => event.stopPropagation()}
            className="mt-3 flex items-center overflow-hidden rounded-lg border transition-colors hover:bg-accent focus-visible:bg-accent"
            data-test="link-preview"
        >
            {imageUrl && (
                <img
                    src={imageUrl}
                    alt=""
                    loading="lazy"
                    referrerPolicy="no-referrer"
                    onError={() => setIsImageBroken(true)}
                    className="my-2 ml-2 size-20 shrink-0 rounded-md bg-muted object-cover"
                />
            )}

            <div className="flex min-w-0 flex-1 flex-col justify-center px-3 py-2">
                <span className="truncate font-mono text-[13px] text-muted-foreground">
                    {preview.site_name ?? hostname(preview.url)}
                </span>
                <span className="line-clamp-2 text-[15px] leading-snug font-medium break-words">
                    {preview.title}
                </span>
                {preview.description && (
                    <span className="mt-0.5 line-clamp-2 text-sm leading-snug break-words text-muted-foreground">
                        {preview.description}
                    </span>
                )}
            </div>
        </a>
    );
}
