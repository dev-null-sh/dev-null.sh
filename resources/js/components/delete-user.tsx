import { Form, usePage } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
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
import { clearRecentSearches } from '@/hooks/use-recent-searches';

export default function DeleteUser() {
    const user = usePage().props.auth.user;

    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title="Delete account"
                description={
                    <WordmarkText>
                        Removes your /dev/null account, every commit and reply
                        you've made, and everyone you follow. Replies others
                        left under your commits go too. Replies under your
                        replies stay, hanging off a [deleted] tombstone. Your
                        GitHub account is not touched.
                    </WordmarkText>
                }
            />

            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="outline"
                        className="border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        data-test="delete-user-button"
                    >
                        Delete account
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>Delete your account?</DialogTitle>
                    <DialogDescription>
                        Your profile, commits, replies, and follows go to{' '}
                        <WordmarkText>/dev/null</WordmarkText>, along with every
                        reply others left under your commits. Replies under your
                        replies stay, under a [deleted] tombstone. This can't be
                        undone. You can sign in with GitHub again later, but
                        you'll start from zero.
                    </DialogDescription>

                    <Form
                        {...UserController.destroy.form()}
                        onSuccess={() => user && clearRecentSearches(user.id)}
                    >
                        {({ processing }) => (
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">
                                        Keep my account
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
        </section>
    );
}
