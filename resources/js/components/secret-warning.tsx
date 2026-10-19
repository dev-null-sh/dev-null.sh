import type { Secret } from '@sanity-labs/secret-scan';
import { ChevronsDownUp, ShieldAlert } from 'lucide-react';
import type { RefObject } from 'react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { removeSecrets } from '@/lib/sensitive-text';
import { cn } from '@/lib/utils';

export function SecretWarning({
    body,
    findings,
    textarea,
    onChange,
    confirming,
    onConfirmingChange,
    onApprove,
    onEdit,
}: {
    body: string;
    findings: Secret[];
    textarea: RefObject<HTMLTextAreaElement | null>;
    onChange: (body: string) => void;
    confirming: boolean;
    onConfirmingChange: (open: boolean) => void;
    onApprove: () => void;
    onEdit?: () => void;
}) {
    const [minimized, setMinimized] = useState(false);

    useEffect(() => {
        if (findings.length === 0) {
            setMinimized(false);
        }
    }, [findings.length]);

    if (findings.length === 0) return null;

    const remove = () => {
        onEdit?.();
        onChange(removeSecrets(body));
        onConfirmingChange(false);
        setMinimized(false);
        requestAnimationFrame(() => textarea.current?.focus());
    };

    return (
        <>
            {minimized ? (
                <button
                    type="button"
                    aria-label="Sensitive text warning"
                    aria-expanded={false}
                    title="Possible secrets detected"
                    data-test="secret-warning-minimized"
                    onClick={() => setMinimized(false)}
                    className={cn(
                        'my-2 flex size-8 items-center justify-center rounded-md border border-amber-600/30 bg-amber-500/8 text-amber-700 transition-colors',
                        'hover:bg-amber-500/15 focus-visible:outline-2 focus-visible:outline-ring dark:text-amber-400',
                    )}
                >
                    <ShieldAlert className="size-4" />
                </button>
            ) : (
                <section
                    aria-label="Sensitive text warning"
                    aria-expanded={true}
                    className="my-2 rounded-md border border-amber-600/30 bg-amber-500/8 p-3 text-sm"
                >
                    <div className="flex items-start gap-2">
                        <p
                            className="flex min-w-0 flex-1 items-center gap-2 font-medium"
                            role="status"
                        >
                            <ShieldAlert className="size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                            Possible{' '}
                            {findings.length === 1 ? 'secret' : 'secrets'}{' '}
                            detected
                        </p>
                        <button
                            type="button"
                            aria-label="Minimize secret warning"
                            data-test="secret-warning-minimize"
                            onClick={() => setMinimized(true)}
                            className="rounded p-0.5 text-muted-foreground transition-colors hover:bg-amber-500/15 hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            <ChevronsDownUp className="size-4" />
                        </button>
                    </div>
                    <p className="mt-1 text-muted-foreground">
                        Checked on your device. This draft won't be saved while
                        it contains a detected secret.
                    </p>
                    <ul className="mt-2 space-y-1">
                        {findings.map((finding, index) => (
                            <li key={`${finding.rule}-${finding.start}`}>
                                <button
                                    type="button"
                                    className="rounded text-left text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                                    onClick={() => {
                                        onEdit?.();
                                        requestAnimationFrame(() => {
                                            textarea.current?.focus();
                                            textarea.current?.setSelectionRange(
                                                finding.start,
                                                finding.end,
                                            );
                                        });
                                    }}
                                >
                                    Select possible secret{' '}
                                    {findings.length > 1 ? index + 1 : ''}
                                </button>
                            </li>
                        ))}
                    </ul>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="mt-2"
                        onClick={remove}
                    >
                        Remove detected secrets
                    </Button>
                </section>
            )}
            <Dialog open={confirming} onOpenChange={onConfirmingChange}>
                <DialogContent>
                    <DialogTitle>Publish possible secrets?</DialogTitle>
                    <DialogDescription>
                        This text may contain credentials. Remove them before
                        publishing, or continue only if these are safe examples.
                    </DialogDescription>
                    <DialogFooter className="flex-col sm:flex-row">
                        <DialogClose asChild>
                            <Button type="button" variant="ghost">
                                Keep editing
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={remove}
                        >
                            Remove secrets
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => {
                                onApprove();
                                textarea.current?.form?.requestSubmit();
                            }}
                        >
                            Publish anyway
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
