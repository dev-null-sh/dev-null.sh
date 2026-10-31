import { Form, Head, Link, usePage } from '@inertiajs/react';
import TermsController from '@/actions/App/Http/Controllers/TermsController';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { ErrorMessage } from '@/components/error-message';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { WordmarkText } from '@/components/wordmark-text';
import { useDateTime } from '@/hooks/use-date-time';
import { clearRecentSearches } from '@/hooks/use-recent-searches';
import { logout } from '@/routes';

/**
 * Where a banned user is sent from everywhere else: why they were banned, and deleting their account as the way out.
 */
export default function Banned({
    reason,
    bannedAt,
}: {
    reason: string;
    bannedAt: string;
}) {
    const user = usePage().props.auth.user;
    const { formatFullDate } = useDateTime();

    return (
        <>
            <Head title="Banned" />

            <main className="mx-auto flex min-h-svh w-full max-w-3xl items-center px-4 py-16 sm:px-8">
                <ErrorMessage
                    command={`remote: Permission to /dev/null denied to ${user?.handle ?? 'you'}: banned.`}
                    headline={<h1>Your account is banned.</h1>}
                    description={
                        <div className="grid gap-3">
                            <p>
                                A moderator banned you on{' '}
                                {formatFullDate(bannedAt)}. Reason:{' '}
                                <span className="text-foreground">
                                    {reason}
                                </span>
                                . You can't commit, reply, star, follow, or read
                                your log anymore.
                            </p>
                            <p>
                                All that's left is deleting your account. It
                                removes your profile, commits, replies, and
                                follows. Your GitHub account is not touched, but
                                it can't sign up again. See the{' '}
                                <Link
                                    href={`${TermsController.show.url()}#rules`}
                                    className="text-foreground underline underline-offset-4"
                                >
                                    terms
                                </Link>
                                .
                            </p>
                        </div>
                    }
                >
                    <div className="mt-8 flex flex-wrap gap-2">
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button
                                    size="lg"
                                    variant="destructive"
                                    data-test="delete-user-button"
                                >
                                    Delete account
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Delete your account?</DialogTitle>
                                <DialogDescription>
                                    Your profile, commits, replies, and follows
                                    go to <WordmarkText>/dev/null</WordmarkText>
                                    , along with every reply others left under
                                    your commits. This can't be undone.
                                </DialogDescription>

                                <Form
                                    {...UserController.destroy.form()}
                                    onSuccess={() =>
                                        user && clearRecentSearches(user.id)
                                    }
                                >
                                    {({ processing }) => (
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="outline">
                                                    Cancel
                                                </Button>
                                            </DialogClose>

                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                disabled={processing}
                                                data-test="confirm-delete-user-button"
                                            >
                                                Delete account
                                            </Button>
                                        </DialogFooter>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>

                        <Button size="lg" variant="outline" asChild>
                            <Link href={logout()} as="button">
                                Sign out
                            </Link>
                        </Button>
                    </div>
                </ErrorMessage>
            </main>
        </>
    );
}
