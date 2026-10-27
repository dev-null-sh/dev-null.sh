import { router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { cn } from '@/lib/utils';
import { home } from '@/routes';

export function ProfileBackButton({ className }: { className?: string }) {
    const { auth } = usePage().props;

    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(auth.log === null ? home() : LogController.show(auth.log));
    };

    return (
        <button
            type="button"
            onClick={goBack}
            aria-label="Back"
            className={cn(
                'text-muted-foreground hover:text-foreground',
                className,
            )}
        >
            <ArrowLeft className="size-4" />
        </button>
    );
}
