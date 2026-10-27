import { ArrowLeft } from 'lucide-react';
import { useGoBack } from '@/hooks/use-go-back';
import { cn } from '@/lib/utils';

export function ProfileBackButton({ className }: { className?: string }) {
    const goBack = useGoBack();

    return (
        <button
            type="button"
            onClick={goBack}
            aria-label="Back"
            className={cn(
                '-m-3 inline-flex shrink-0 items-center justify-center p-3 text-muted-foreground transition-colors hover:text-foreground',
                className,
            )}
        >
            <ArrowLeft className="size-4" />
        </button>
    );
}
