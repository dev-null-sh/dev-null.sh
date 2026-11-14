import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/GuildController';
import { store } from '@/actions/App/Http/Controllers/GuildReportController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { Guild } from '@/types/guild';

export function GuildReportButton({ guild }: { guild: Guild }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Report guild</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Report {guild.name}</DialogTitle>
                    <DialogDescription>
                        Your report is private. Admins will review the
                        description and icon. Reporting does not hide the guild.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...store.form(guild.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <Label htmlFor="guild-report-details">
                                What looks wrong?
                            </Label>
                            <textarea
                                id="guild-report-details"
                                name="details"
                                required
                                maxLength={500}
                                rows={4}
                                className="mt-2 w-full resize-none overflow-x-hidden rounded-md border border-input bg-transparent px-3 py-2 text-base focus-visible:outline-ring md:text-sm"
                                aria-invalid={!!errors.details}
                                aria-describedby="guild-report-error"
                            />
                            <div className="min-h-8">
                                <InputError
                                    id="guild-report-error"
                                    message={errors.details}
                                />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Send report
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function GuildRemovalButton({
    guild,
}: {
    guild: Pick<Guild, 'id' | 'handle' | 'name'>;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" className="text-destructive">
                    Remove moderator
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove {guild.name}’s moderator?</DialogTitle>
                    <DialogDescription>
                        This is the guild’s last moderator. Removing them hides
                        the guild page until a different moderator claims #
                        {guild.handle}. Its icon, description, memberships and
                        mute preferences will be removed. Commits keep their
                        hashtag. This moderator cannot reclaim the guild.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...destroy.form(guild.id)}
                    onSuccess={() => {
                        setOpen(false);
                        router.flushAll();
                    }}
                >
                    {({ processing }) => (
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Remove moderator
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
