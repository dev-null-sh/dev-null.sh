import { Form } from '@inertiajs/react';
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

export default function DeleteUser() {
    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title="Delete account"
                description="Removes your devnull account, every commit and reply you've made, and everyone you follow. Replies others left under your commits go too. Replies under your replies stay, hanging off a [deleted] tombstone. Your GitHub account is not touched."
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
                        Your profile, commits, replies, and follows go to
                        /dev/null, along with every reply others left under your
                        commits. Replies under your replies stay, under a
                        [deleted] tombstone. This can't be undone. You can sign
                        in with GitHub again later, but you'll start from zero.
                    </DialogDescription>

                    <Form {...UserController.destroy.form()}>
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
