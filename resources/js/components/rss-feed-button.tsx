import { Check, Rss } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

export function RssFeedButton({
    feedUrl,
    className,
}: {
    feedUrl: string;
    className?: string;
}) {
    const [, copy] = useClipboard();
    const [isCopied, setIsCopied] = useState(false);

    useEffect(() => {
        if (!isCopied) {
            return;
        }

        const timeout = setTimeout(() => setIsCopied(false), 2000);

        return () => clearTimeout(timeout);
    }, [isCopied]);

    const copyFeedUrl = async () => {
        if (await copy(feedUrl)) {
            setIsCopied(true);
            toast(
                <span className="font-mono text-[13px]">
                    Copied your RSS link. Keep it private.
                </span>,
            );

            return;
        }

        toast.error(
            <span className="font-mono text-[13px]">
                fatal: could not copy your RSS link
            </span>,
        );
    };

    const Icon = isCopied ? Check : Rss;

    return (
        <Button
            type="button"
            variant="outline"
            onClick={copyFeedUrl}
            title="Copy the RSS link to the commits of everyone you follow"
            className={className}
            data-test="rss-feed-button"
        >
            <Icon />
            RSS
        </Button>
    );
}
