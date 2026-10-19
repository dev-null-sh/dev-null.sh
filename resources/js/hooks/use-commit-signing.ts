import type { FormComponentRef } from '@inertiajs/core';
import { usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { RefObject } from 'react';
import { toast } from 'sonner';
import { signCommit } from '@/lib/passkeys';
import type { CommitDraft, PasskeySignature } from '@/lib/passkeys';

/**
 * An Inertia visit can't wait for a passkey, so the first submit stops while the passkey signs, and the form is
 * submitted again with the signature. The draft is read in the transform, because by onBefore a commit with images is
 * already FormData.
 */
export function useCommitSigning(form: RefObject<FormComponentRef | null>) {
    const { auth } = usePage().props;
    const signature = useRef<PasskeySignature | null>(null);
    const draft = useRef<object>({});
    const [isSigning, setIsSigning] = useState(false);

    return {
        isSigning,
        withSignature: <T extends object>(data: T) => {
            if (signature.current === null) {
                draft.current = data;

                return data;
            }

            return { ...data, signature: signature.current };
        },
        beforeVisit: (check: () => boolean): boolean => {
            if (signature.current !== null) {
                signature.current = null;

                return true;
            }

            if (!check()) {
                return false;
            }

            if (!auth.requiresSignedCommits) {
                return true;
            }

            setIsSigning(true);
            signCommit(draftOf(draft.current))
                .then((signed) => {
                    signature.current = signed;
                    form.current?.submit();
                })
                .catch((error: unknown) => {
                    toast.error(
                        error instanceof Error &&
                            error.name !== 'NotAllowedError'
                            ? error.message
                            : 'The commit was not signed, so it was not posted.',
                    );
                })
                .finally(() => setIsSigning(false));

            return false;
        },
    };
}

function draftOf(data: object): CommitDraft {
    const fields = data as Record<string, unknown>;
    const poll = fields.poll as CommitDraft['poll'] | undefined;

    return {
        body: typeof fields.body === 'string' ? fields.body : '',
        quoted_commit_id:
            typeof fields.quoted_commit_id === 'string'
                ? fields.quoted_commit_id
                : null,
        scheduled_at:
            typeof fields.scheduled_at === 'string'
                ? fields.scheduled_at
                : null,
        poll: poll && Array.isArray(poll.options) ? poll : null,
        images: Array.isArray(fields.images)
            ? fields.images.filter((image) => image instanceof File)
            : [],
    };
}
