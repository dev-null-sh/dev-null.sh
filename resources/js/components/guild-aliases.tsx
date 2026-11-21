import { Form, router } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/GuildAliasController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { Guild } from '@/types/guild';

export function GuildAliasesButton({ guild }: { guild: Guild }) {
    function remove(alias: string) {
        router
            .optimistic<{ guilds: Guild[] | null }>((props) => ({
                guilds: props.guilds?.map((item) =>
                    item.id === guild.id
                        ? {
                              ...item,
                              aliases: item.aliases.filter(
                                  (other) => other !== alias,
                              ),
                          }
                        : item,
                ),
            }))
            .delete(destroy.url({ guild: guild.handle, alias }), {
                preserveScroll: true,
                onSuccess: () => router.flushAll(),
            });
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">Aliases</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Aliases of #{guild.handle}</DialogTitle>
                    <DialogDescription>
                        An alias is another hashtag that leads here: commits
                        with it appear on this board, and it links to this
                        guild. A hashtag that already has a guild cannot be an
                        alias.
                    </DialogDescription>
                </DialogHeader>
                <ul
                    aria-label="Aliases"
                    className="flex min-h-9 flex-wrap items-center gap-2"
                >
                    {guild.aliases.map((alias) => (
                        <li
                            key={alias}
                            className="flex max-w-full min-w-0 items-center rounded-md border font-mono text-[13px]"
                        >
                            <span
                                className="min-w-0 truncate py-1.5 pl-2.5"
                                title={`#${alias}`}
                            >
                                #{alias}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-9 shrink-0 text-muted-foreground"
                                aria-label={`Remove #${alias}`}
                                title={`Remove #${alias}`}
                                onClick={() => remove(alias)}
                            >
                                <X aria-hidden className="size-4" />
                            </Button>
                        </li>
                    ))}
                    {guild.aliases.length === 0 && (
                        <li className="text-sm text-muted-foreground">
                            No aliases yet.
                        </li>
                    )}
                </ul>
                <Form
                    {...store.form(guild.handle)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => router.flushAll()}
                    resetOnSuccess
                >
                    {({ errors, processing }) => (
                        <>
                            <Label htmlFor="guild-alias">Add an alias</Label>
                            <div className="mt-2 flex gap-2">
                                <input
                                    id="guild-alias"
                                    name="handle"
                                    required
                                    maxLength={61}
                                    placeholder="#hashtag"
                                    autoCapitalize="none"
                                    autoCorrect="off"
                                    spellCheck={false}
                                    className="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 font-mono text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                    aria-invalid={!!errors.handle}
                                    aria-describedby="guild-alias-error"
                                />
                                <Button
                                    type="submit"
                                    className="shrink-0"
                                    disabled={processing}
                                >
                                    <Plus aria-hidden className="size-4" />
                                    Add
                                </Button>
                            </div>
                            <div className="min-h-10 pt-2" aria-live="polite">
                                <InputError
                                    id="guild-alias-error"
                                    message={errors.handle}
                                />
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
