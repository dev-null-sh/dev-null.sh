import { useLang } from '@erag/lang-sync-inertia/react';
import type { Secret } from '@sanity-labs/secret-scan';
import { ShieldAlert } from 'lucide-react';
import type { RefObject } from 'react';
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
    const { __, transChoice } = useLang();

    if (findings.length === 0) return null;

    const remove = () => {
        onEdit?.();
        onChange(removeSecrets(body));
        onConfirmingChange(false);
        requestAnimationFrame(() => textarea.current?.focus());
    };

    return (
        <>
            <section
                aria-label={__('composer.secrets.warning')}
                className="my-2 rounded-md border border-amber-600/30 bg-amber-500/8 p-3 text-sm"
            >
                <p
                    className="flex items-center gap-2 font-medium"
                    role="status"
                >
                    <ShieldAlert className="size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                    {transChoice('composer.secrets.detected', findings.length)}
                </p>
                <p className="mt-1 text-muted-foreground">
                    {__('composer.secrets.checked_locally')}
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
                                {findings.length > 1
                                    ? __('composer.secrets.select_numbered', {
                                          number: index + 1,
                                      })
                                    : __('composer.secrets.select')}
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
                    {__('composer.secrets.remove_detected')}
                </Button>
            </section>
            <Dialog open={confirming} onOpenChange={onConfirmingChange}>
                <DialogContent>
                    <DialogTitle>
                        {__('composer.secrets.confirm_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {__('composer.secrets.confirm_description')}
                    </DialogDescription>
                    <DialogFooter className="flex-col sm:flex-row">
                        <DialogClose asChild>
                            <Button type="button" variant="ghost">
                                {__('composer.secrets.keep_editing')}
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={remove}
                        >
                            {__('composer.secrets.remove')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => {
                                onApprove();
                                textarea.current?.form?.requestSubmit();
                            }}
                        >
                            {__('composer.secrets.publish_anyway')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
