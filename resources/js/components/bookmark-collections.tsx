import { Form, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import BookmarkCollectionController from '@/actions/App/Http/Controllers/BookmarkCollectionController';
import BookmarkController from '@/actions/App/Http/Controllers/BookmarkController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { BookmarkCollection } from '@/types';

const MAX_NAME_LENGTH = 50;

const chip =
    'flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

export function BookmarkCollectionBar({
    collections,
    selectedId,
}: {
    collections: BookmarkCollection[];
    selectedId: string | null;
}) {
    const [isCreating, setIsCreating] = useState(false);

    return (
        <div className="flex items-center gap-2 border-b px-4 py-2.5 sm:px-6">
            <nav
                aria-label="Collections"
                className="-my-1 flex min-w-0 flex-1 [scrollbar-width:none] items-center gap-2 overflow-x-auto py-1"
            >
                <Link
                    href={BookmarkController.index()}
                    aria-current={selectedId === null ? 'page' : undefined}
                    className={cn(chip, 'text-muted-foreground', {
                        'border-primary/40 bg-primary-soft text-foreground':
                            selectedId === null,
                    })}
                    data-test="bookmark-collection-all"
                >
                    All saved
                </Link>
                {collections.map((collection) => {
                    const isSelected = collection.id === selectedId;

                    return (
                        <Link
                            key={collection.id}
                            href={BookmarkCollectionController.show(collection)}
                            aria-current={isSelected ? 'page' : undefined}
                            title={collection.name}
                            className={cn(chip, 'text-muted-foreground', {
                                'border-primary/40 bg-primary-soft text-foreground':
                                    isSelected,
                            })}
                            data-test="bookmark-collection-link"
                        >
                            <span className="max-w-40 truncate">
                                {collection.name}
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {collection.commits_count}
                            </span>
                        </Link>
                    );
                })}
            </nav>

            <Button
                variant="ghost"
                size="sm"
                className="shrink-0"
                onClick={() => setIsCreating(true)}
                data-test="create-bookmark-collection-button"
            >
                <Plus aria-hidden />
                <span className="sr-only sm:not-sr-only">New collection</span>
            </Button>

            <CollectionNameDialog
                open={isCreating}
                onOpenChange={setIsCreating}
                title="New collection"
                description="Group saved commits privately. Only you can see your collections."
                form={BookmarkCollectionController.store.form()}
                submitLabel="Create"
            />
        </div>
    );
}

export function BookmarkCollectionHeader({
    collection,
}: {
    collection: Pick<BookmarkCollection, 'id' | 'name'>;
}) {
    const [dialog, setDialog] = useState<'rename' | 'delete' | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const changeOpen = (name: 'rename' | 'delete', open: boolean) =>
        setDialog(open ? name : null);

    const deleteCollection = () => {
        router.visit(BookmarkCollectionController.destroy(collection), {
            onStart: () => setIsDeleting(true),
            onFinish: () => setIsDeleting(false),
        });
    };

    return (
        <div className="flex min-h-12 items-center gap-2 border-b px-4 py-2 sm:px-6">
            <h2
                className="mr-auto min-w-0 truncate text-sm font-medium"
                title={collection.name}
            >
                {collection.name}
            </h2>
            <Button
                variant="ghost"
                size="sm"
                onClick={() => setDialog('rename')}
                data-test="rename-bookmark-collection-button"
            >
                <Pencil aria-hidden />
                Rename
            </Button>
            <Button
                variant="ghost"
                size="sm"
                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                onClick={() => setDialog('delete')}
                data-test="delete-bookmark-collection-button"
            >
                <Trash2 aria-hidden />
                Delete
            </Button>

            <CollectionNameDialog
                open={dialog === 'rename'}
                onOpenChange={(open) => changeOpen('rename', open)}
                title="Rename collection"
                form={BookmarkCollectionController.update.form(collection)}
                defaultValue={collection.name}
                submitLabel="Rename"
            />

            <Dialog
                open={dialog === 'delete'}
                onOpenChange={(open) => changeOpen('delete', open)}
            >
                <DialogContent>
                    <DialogTitle className="min-w-0 break-words">
                        Delete “{collection.name}”?
                    </DialogTitle>
                    <DialogDescription>
                        Only the collection goes. Its commits stay saved and in
                        any other collections.
                    </DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={isDeleting}
                            onClick={deleteCollection}
                            className="min-w-24"
                            data-test="confirm-delete-bookmark-collection-button"
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function CollectionNameDialog({
    open,
    onOpenChange,
    title,
    description,
    form,
    defaultValue = '',
    submitLabel,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    form: { action: string; method: 'get' | 'post' };
    defaultValue?: string;
    submitLabel: string;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                {description ? (
                    <DialogDescription>{description}</DialogDescription>
                ) : (
                    <DialogDescription className="sr-only">
                        Choose a name for this collection.
                    </DialogDescription>
                )}

                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="bookmark-collection-name">
                                    Name
                                </Label>
                                <Input
                                    id="bookmark-collection-name"
                                    name="name"
                                    defaultValue={defaultValue}
                                    maxLength={MAX_NAME_LENGTH}
                                    required
                                    autoFocus
                                    autoComplete="off"
                                    placeholder="Laravel tips"
                                    aria-invalid={
                                        errors.name ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.name
                                            ? 'bookmark-collection-name-error'
                                            : undefined
                                    }
                                    className="text-base md:text-sm"
                                />
                                <InputError
                                    id="bookmark-collection-name-error"
                                    message={errors.name}
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-w-24"
                                    data-test="submit-bookmark-collection-button"
                                >
                                    {submitLabel}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
