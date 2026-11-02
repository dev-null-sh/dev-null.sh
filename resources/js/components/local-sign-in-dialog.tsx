import { Form, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Locally, sign-in links come back with a flash that opens this modal instead of going to GitHub.
 * The route only exists locally, so its URL is written out: Wayfinder would not generate it in other environments.
 */
export function LocalSignInDialog() {
    const { flash } = usePage();
    const [open, setOpen] = useState(flash.localSignIn === true);

    useEffect(() => {
        return router.on('flash', (event) => {
            if (event.detail.flash.localSignIn) {
                setOpen(true);
            }
        });
    }, []);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-sm">
                <DialogTitle>Sign in locally</DialogTitle>
                <DialogDescription>
                    Sign in as any handle. A new one creates the user.
                </DialogDescription>

                <Form action="/login/local" method="post" className="space-y-3">
                    {({ errors, processing }) => (
                        <>
                            <label htmlFor="local-handle" className="sr-only">
                                Username
                            </label>
                            <div className="flex h-11 items-center rounded-lg border bg-background focus-within:border-ring">
                                <span className="pl-3 font-mono text-muted-foreground">
                                    @
                                </span>
                                <input
                                    id="local-handle"
                                    name="handle"
                                    type="text"
                                    required
                                    autoFocus
                                    autoComplete="username"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                    maxLength={39}
                                    placeholder="local-dev"
                                    aria-invalid={!!errors.handle}
                                    className="size-full bg-transparent pr-3 pl-1 font-mono text-base outline-none placeholder:text-muted-foreground md:text-[15px]"
                                    data-test="local-sign-in-handle"
                                />
                            </div>
                            <InputError message={errors.handle} />
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    Sign in
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
