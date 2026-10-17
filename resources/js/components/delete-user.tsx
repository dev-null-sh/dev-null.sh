import { useLang } from '@erag/lang-sync-inertia/react';
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
    const { __ } = useLang();

    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title={__('settings.delete_account.title')}
                description={__('settings.delete_account.description')}
            />

            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="outline"
                        className="border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        data-test="delete-user-button"
                    >
                        {__('settings.delete_account.title')}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>
                        {__('settings.delete_account.confirm_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {__('settings.delete_account.confirm_description')}
                    </DialogDescription>

                    <Form {...UserController.destroy.form()}>
                        {({ processing }) => (
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">
                                        {__('settings.delete_account.keep')}
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="confirm-delete-user-button"
                                >
                                    {__('settings.delete_account.title')}
                                </Button>
                            </DialogFooter>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </section>
    );
}
