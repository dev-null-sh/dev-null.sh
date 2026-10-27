import { Form, Link, router, usePage } from '@inertiajs/react';
import PrivacyController from '@/actions/App/Http/Controllers/PrivacyController';
import TermsController from '@/actions/App/Http/Controllers/TermsController';
import UserTermsController from '@/actions/App/Http/Controllers/UserTermsController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { WordmarkText } from '@/components/wordmark-text';
import { clearRecentSearches } from '@/hooks/use-recent-searches';
import { clearStashes } from '@/hooks/use-stash';
import { TERMS_EFFECTIVE_DATE } from '@/lib/legal';
import { logout } from '@/routes';

/**
 * Until they accept the terms and privacy policy in force, a signed-in person can't use the web: the dialog can't be
 * dismissed, and a new version brings it back. The terms and privacy pages open in a new tab, where it isn't shown.
 */
export function TermsDialog() {
    const { component, props } = usePage();
    const { user, termsToAccept } = props.auth;

    if (
        user === null ||
        termsToAccept === null ||
        component === 'terms/show' ||
        component === 'privacy/show'
    ) {
        return null;
    }

    const linkClassName = 'text-foreground underline underline-offset-4';

    return (
        <Dialog open>
            <DialogContent
                showCloseButton={false}
                onEscapeKeyDown={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                data-test="terms-dialog"
            >
                <DialogTitle>
                    {termsToAccept.updated
                        ? 'We updated our terms'
                        : 'Terms and privacy'}
                </DialogTitle>
                <DialogDescription asChild>
                    <div className="grid gap-3">
                        <p>
                            <WordmarkText>
                                {termsToAccept.updated
                                    ? `The terms of service and the privacy policy changed on ${TERMS_EFFECTIVE_DATE}. To keep using /dev/null, read them and accept them.`
                                    : 'To keep using /dev/null, read and accept the terms of service and the privacy policy.'}
                            </WordmarkText>
                        </p>
                        <p>
                            Read the{' '}
                            <Link
                                href={TermsController.show.url()}
                                target="_blank"
                                className={linkClassName}
                            >
                                terms of service
                            </Link>{' '}
                            and the{' '}
                            <Link
                                href={PrivacyController.show.url()}
                                target="_blank"
                                className={linkClassName}
                            >
                                privacy policy
                            </Link>
                            .
                        </p>
                    </div>
                </DialogDescription>

                <Form
                    {...UserTermsController.update.form()}
                    options={{ preserveScroll: true, preserveState: true }}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="version"
                                value={termsToAccept.version}
                            />
                            <InputError message={errors.version} />
                            <DialogFooter className="gap-2">
                                <Button
                                    variant="outline"
                                    asChild
                                    disabled={processing}
                                >
                                    <Link
                                        href={logout()}
                                        as="button"
                                        type="button"
                                        onClick={() => {
                                            clearStashes(user.id);
                                            clearRecentSearches(user.id);
                                            router.flushAll();
                                        }}
                                    >
                                        Log out
                                    </Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="accept-terms-button"
                                >
                                    I accept
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
