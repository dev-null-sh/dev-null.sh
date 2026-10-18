import { ExternalLink } from 'lucide-react';
import { LinkEmbed } from '@/components/link-embed';
import type { Embed } from '@/types';

export function TalkMedia({
    url,
    embed,
    title,
    label,
}: {
    url: string;
    embed: Embed | null | undefined;
    title: string;
    label: string;
}) {
    if (embed) {
        return (
            <LinkEmbed
                preview={{
                    url,
                    title,
                    description: null,
                    image_url: null,
                    site_name: embed.provider,
                    is_pending: false,
                    embed,
                }}
                embed={embed}
            />
        );
    }

    return (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 font-mono text-xs text-muted-foreground hover:text-foreground hover:underline"
        >
            <ExternalLink className="size-3.5" />
            {label}
        </a>
    );
}
