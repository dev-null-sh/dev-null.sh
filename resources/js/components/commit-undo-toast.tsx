import { router, usePage } from '@inertiajs/react';
import { Undo2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import CommitRestorationController from '@/actions/App/Http/Controllers/CommitRestorationController';
import { Button } from '@/components/ui/button';
import { commitMutationOptions } from '@/lib/commit-mutation-options';
import { shortHash } from '@/lib/utils';
import type { DeletedFlash } from '@/types';

export function CommitUndoToast({
    deleted,
    toastId,
}: {
    deleted: DeletedFlash;
    toastId: string | number;
}) {
    const { component } = usePage();
    const [deadline] = useState(
        () =>
            Date.now() +
            Date.parse(deleted.expires_at) -
            Date.parse(deleted.server_time),
    );
    const [remaining, setRemaining] = useState(() =>
        Math.max(0, Math.ceil((deadline - Date.now()) / 1000)),
    );
    const [processing, setProcessing] = useState(false);
    const submitting = useRef(false);

    useEffect(() => {
        const update = () =>
            setRemaining(
                Math.max(0, Math.ceil((deadline - Date.now()) / 1000)),
            );
        const interval = window.setInterval(update, 250);
        return () => window.clearInterval(interval);
    }, [deadline]);

    useEffect(() => {
        if (remaining === 0 && !processing) {
            toast('Undo window expired.', { id: toastId, duration: 4000 });
        }
    }, [remaining, processing, toastId]);

    const undo = () => {
        if (submitting.current || Date.now() >= deadline) {
            return;
        }

        submitting.current = true;
        setProcessing(true);

        router.visit(CommitRestorationController.store(deleted.id), {
            ...commitMutationOptions(component),
            onSuccess: (page) => {
                toast.dismiss(toastId);
                if (page.flash.restored) {
                    toast.success('Commit restored.');
                }
            },
            onError: (errors) => {
                toast.dismiss(toastId);
                toast.error(errors.undo ?? 'Unable to restore this commit.');
            },
            onFinish: () => {
                submitting.current = false;
                setProcessing(false);
            },
        });
    };

    return (
        <div className="flex w-full items-center gap-4" data-test="commit-undo">
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">Commit deleted.</p>
                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                    {shortHash(deleted.id)} ·{' '}
                    <span aria-hidden>{remaining}s to undo</span>
                    <span className="sr-only">
                        Undo is available for 30 seconds after deletion.
                    </span>
                </p>
            </div>
            <Button
                size="sm"
                variant="outline"
                onClick={undo}
                disabled={processing || remaining === 0}
            >
                <Undo2 className="size-3.5" />
                {processing ? 'Restoring…' : 'Undo'}
            </Button>
        </div>
    );
}
