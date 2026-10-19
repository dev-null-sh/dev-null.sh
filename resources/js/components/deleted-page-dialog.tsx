import * as DialogPrimitive from '@radix-ui/react-dialog';
import { ErrorMessage, useHomeAction } from '@/components/error-message';
import { shortHash } from '@/lib/utils';

export type DeletedPage =
    | { type: 'commit'; id: string }
    | { type: 'photo'; id: string }
    | { type: 'page'; path: string };

function deletedPageCopy(page: DeletedPage) {
    switch (page.type) {
        case 'commit':
            return {
                command: `fatal: bad object ${shortHash(page.id)}`,
                headline: 'This commit is no longer available.',
                description:
                    "It was deleted or hidden while you were reading it, so you can't reply to it, star it or share it anymore.",
            };
        case 'photo':
            return {
                command: `fatal: bad object ${shortHash(page.id)}`,
                headline: 'This photo is no longer available.',
                description:
                    'Its commit was deleted or hidden while you were looking at it.',
            };
        case 'page':
            return {
                command: `fatal: path '${page.path}' does not exist in 'HEAD'`,
                headline: 'This page is no longer available.',
                description:
                    'Whatever was here was deleted or hidden while you were looking at it.',
            };
    }
}

/**
 * Covers a page whose commit went away while it was open. It can't be dismissed, so nothing on the page can be acted
 * on anymore: the only ways out lead elsewhere.
 */
export function DeletedPageDialog({ page }: { page: DeletedPage }) {
    const copy = deletedPageCopy(page);
    const action = useHomeAction();

    return (
        <DialogPrimitive.Root open>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-60 animate-in bg-background/90 backdrop-blur-[3px] fade-in-0" />
                <DialogPrimitive.Content
                    data-test="deleted-page-dialog"
                    onEscapeKeyDown={(event) => event.preventDefault()}
                    onInteractOutside={(event) => event.preventDefault()}
                    className="fixed inset-0 z-60 flex h-dvh animate-in overflow-y-auto overscroll-contain px-4 py-16 fade-in-0 outline-none sm:px-8"
                >
                    <ErrorMessage
                        className="m-auto max-w-3xl"
                        command={copy.command}
                        headline={
                            <DialogPrimitive.Title>
                                {copy.headline}
                            </DialogPrimitive.Title>
                        }
                        description={
                            <DialogPrimitive.Description>
                                {copy.description}
                            </DialogPrimitive.Description>
                        }
                        action={action}
                        logoClassName="text-[1.75rem] font-bold tracking-[-0.03em]"
                    />
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
