import { Form } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useRef } from 'react';
import { update } from '@/actions/App/Http/Controllers/UserTrustController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import type { UserModeration } from '@/types';

export function UserTrustControls({
    username,
    moderation,
}: {
    username: string;
    moderation: UserModeration;
}) {
    const isSubmitting = useRef(false);

    return (
        <section
            aria-labelledby="publishing-access"
            className="border-b px-4 py-5 sm:px-6"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2
                    id="publishing-access"
                    className="flex items-center gap-2 text-sm font-semibold"
                >
                    <ShieldCheck aria-hidden className="size-4" />
                    Publishing access
                </h2>
                <span className="text-xs text-muted-foreground">
                    Only administrators
                </span>
            </div>

            <p className="mt-3 text-[15px] font-medium" role="status">
                {moderation.isAdmin
                    ? 'Administrator'
                    : moderation.isTrusted
                      ? 'Trusted author'
                      : 'Requires review'}
            </p>
            <p className="mt-1 text-sm text-muted-foreground">
                {moderation.isAdmin
                    ? 'Administrators already publish immediately. Their access is managed separately.'
                    : moderation.isTrusted
                      ? 'New commits and replies publish immediately. Revoking trust sends future commits back to review.'
                      : 'New commits and replies wait for approval. Trust this author to let them publish immediately.'}
            </p>

            {!moderation.isAdmin && (
                <Form
                    {...update.form({ username })}
                    options={{ preserveScroll: true }}
                    onStart={() => {
                        isSubmitting.current = true;
                    }}
                    className="mt-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="is_trusted"
                                value={moderation.isTrusted ? '0' : '1'}
                            />
                            <Button
                                ref={(button) => {
                                    if (
                                        button &&
                                        !processing &&
                                        isSubmitting.current
                                    ) {
                                        isSubmitting.current = false;

                                        if (
                                            document.activeElement ===
                                            document.body
                                        ) {
                                            button.focus({
                                                preventScroll: true,
                                            });
                                        }
                                    }
                                }}
                                variant="outline"
                                type="submit"
                                disabled={processing}
                            >
                                {processing
                                    ? 'Saving…'
                                    : moderation.isTrusted
                                      ? 'Revoke trust'
                                      : 'Trust author'}
                            </Button>
                            <InputError
                                message={errors.is_trusted}
                                className="mt-2"
                            />
                        </>
                    )}
                </Form>
            )}

            <p className="mt-3 text-xs text-muted-foreground">
                Existing moderation decisions stay unchanged.
            </p>

            {moderation.history.length === 0 ? (
                <p className="mt-4 text-sm text-muted-foreground">
                    No trust decisions yet.
                </p>
            ) : (
                <details className="mt-4 text-sm">
                    <summary className="w-fit cursor-pointer rounded-sm text-muted-foreground hover:text-foreground">
                        Recent trust decisions
                    </summary>
                    <ol className="mt-3 space-y-3">
                        {moderation.history.map((change) => (
                            <li key={change.id}>
                                <p className="flex flex-wrap gap-x-1.5">
                                    <span>
                                        {change.is_trusted
                                            ? 'Trust granted'
                                            : 'Trust revoked'}
                                    </span>
                                    <span className="break-all text-muted-foreground">
                                        {change.actor
                                            ? `by @${change.actor.username}`
                                            : 'by a deleted administrator'}
                                    </span>
                                </p>
                                <time
                                    dateTime={change.created_at}
                                    className="font-mono text-xs text-muted-foreground"
                                >
                                    {new Date(
                                        change.created_at,
                                    ).toLocaleString()}
                                </time>
                            </li>
                        ))}
                    </ol>
                </details>
            )}
        </section>
    );
}
