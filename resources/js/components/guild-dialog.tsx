import type { FormComponentRef } from '@inertiajs/core';
import { Form, router } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type ReactNode, useRef, useState } from 'react';
import { store, update } from '@/actions/App/Http/Controllers/GuildController';
import { EmojiPickerButton } from '@/components/emoji-picker';
import { GuildIcon } from '@/components/guild-icon';
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

const fieldClass =
    'w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

export function GuildDialog({
    handle,
    guild,
    children,
}: {
    handle?: string;
    guild?: Guild;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [emoji, setEmoji] = useState(guild?.emoji ?? '💻');
    const [removeIcon, setRemoveIcon] = useState(false);
    const [name, setName] = useState(handle ?? '');
    const form = useRef<FormComponentRef>(null);
    const nameHandle = name.replace(/[^\p{L}\p{M}\p{N}_]+/gu, '').toLowerCase();
    const hashtag = guild?.handle ?? handle ?? nameHandle;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent
                className="flex flex-col overflow-hidden p-0"
                onCloseAutoFocus={() => {
                    setEmoji(guild?.emoji ?? '💻');
                    setRemoveIcon(false);
                    setName(handle ?? '');
                }}
            >
                <DialogHeader className="shrink-0 px-6 pt-6 text-left">
                    <DialogTitle>
                        {guild ? 'Edit guild' : 'Create a guild'}
                    </DialogTitle>
                    <DialogDescription>
                        {guild
                            ? 'As the moderator, you can change the icon and description.'
                            : handle
                              ? `Give #${handle} a home. Its commits stay public and keep appearing in the log.`
                              : 'Create a public group. Its name determines the hashtag that brings commits into the guild.'}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    ref={form}
                    {...(guild ? update.form(guild.handle) : store.form())}
                    className="flex min-h-0 flex-col"
                    options={{ preserveScroll: true }}
                    onBefore={() => {
                        if (guild) return true;
                        if (handle && nameHandle !== handle) {
                            form.current?.setError(
                                'name',
                                "Guild display name doesn't match hashtag",
                            );
                            return false;
                        }
                        if (
                            !/^\p{L}[\p{L}\p{M}\p{N}_]{0,59}$/u.test(nameHandle)
                        ) {
                            form.current?.setError(
                                'name',
                                'Use a name whose hashtag starts with a letter.',
                            );
                            return false;
                        }
                        return true;
                    }}
                    onSuccess={() => {
                        setOpen(false);
                        router.flushAll();
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid min-h-0 gap-4 overflow-y-auto px-6 py-4">
                                {handle && (
                                    <input
                                        type="hidden"
                                        name="handle"
                                        value={handle}
                                    />
                                )}
                                <input
                                    type="hidden"
                                    name="emoji"
                                    value={emoji}
                                />
                                <input
                                    type="hidden"
                                    name="remove_icon"
                                    value={removeIcon ? '1' : '0'}
                                />
                                <p
                                    className="truncate font-mono text-[13px] text-muted-foreground"
                                    title={hashtag ? `#${hashtag}` : undefined}
                                >
                                    {hashtag
                                        ? `#${hashtag}`
                                        : 'Your hashtag appears here'}
                                </p>
                                {!guild && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="guild-name">
                                            Guild display name
                                        </Label>
                                        <input
                                            id="guild-name"
                                            name="name"
                                            required
                                            maxLength={60}
                                            value={name}
                                            onChange={(event) => {
                                                setName(event.target.value);
                                                form.current?.clearErrors(
                                                    'name',
                                                    'handle',
                                                );
                                            }}
                                            className={fieldClass}
                                            aria-invalid={!!errors.name}
                                            aria-describedby="guild-name-error"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {handle
                                                ? `Match #${handle}’s letters, numbers and underscores. Spaces and decoration are okay.`
                                                : 'Spaces and decoration are okay. Matching hashtags link to this guild.'}
                                        </p>
                                        <div className="min-h-10">
                                            <InputError
                                                id="guild-name-error"
                                                message={
                                                    errors.name ?? errors.handle
                                                }
                                            />
                                        </div>
                                    </div>
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="guild-description">
                                        Description
                                    </Label>
                                    <textarea
                                        id="guild-description"
                                        name="description"
                                        required
                                        maxLength={500}
                                        rows={3}
                                        defaultValue={guild?.description ?? ''}
                                        className={`${fieldClass} resize-none overflow-x-hidden`}
                                        aria-invalid={!!errors.description}
                                        aria-describedby="guild-description-error"
                                    />
                                    <div className="min-h-5">
                                        <InputError
                                            id="guild-description-error"
                                            message={errors.description}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="guild-icon">Icon</Label>
                                    <div className="flex items-center gap-2">
                                        <GuildIcon
                                            guild={{
                                                emoji,
                                                icon_url: removeIcon
                                                    ? null
                                                    : (guild?.icon_url ?? null),
                                            }}
                                            className="size-10 text-2xl"
                                        />
                                        <EmojiPickerButton
                                            onPick={(value) => {
                                                setEmoji(value);
                                                setRemoveIcon(true);
                                            }}
                                        />
                                        <span className="text-sm text-muted-foreground">
                                            Choose an emoji or upload an image.
                                        </span>
                                    </div>
                                    <input
                                        id="guild-icon"
                                        name="images[]"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        className={`${fieldClass} file:mr-3 file:text-sm`}
                                        aria-describedby="guild-icon-help guild-icon-error"
                                    />
                                    <p
                                        id="guild-icon-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        JPEG, PNG or WebP, up to 2 MB. An upload
                                        replaces the emoji.
                                    </p>
                                    <div
                                        id="guild-icon-error"
                                        className="min-h-5"
                                    >
                                        <InputError
                                            message={
                                                errors.emoji ??
                                                errors.images ??
                                                errors['images.0']
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
                            <DialogFooter className="shrink-0 border-t bg-background p-4 sm:px-6">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    <LoaderCircle
                                        aria-hidden
                                        className={`size-4 ${processing ? 'animate-spin' : 'invisible'}`}
                                    />
                                    {guild ? 'Save changes' : 'Create guild'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
