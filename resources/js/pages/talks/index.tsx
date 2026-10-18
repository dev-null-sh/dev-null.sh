import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import TalkController from '@/actions/App/Http/Controllers/TalkController';
import { EmptyFeed } from '@/components/commit-feed';
import InputError from '@/components/input-error';
import { TalkCard } from '@/components/talk-card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useUser } from '@/hooks/use-user';
import AppLayout from '@/layouts/app-layout';
import type { TalkLinkKind, TalkSummary } from '@/types';

const fieldClassName =
    'block w-full rounded-md border bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50 md:text-[15px]';

export default function Index({
    talks,
}: {
    talks: {
        data: TalkSummary[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    const { user } = useUser();
    const [linkKind, setLinkKind] = useState<TalkLinkKind>('slides');

    return (
        <>
            <Head title="Talks" />
            <div className="flex h-14 items-center gap-3 border-b px-4">
                <Link
                    href={`/@${user.username}?tab=talks`}
                    aria-label="Back to profile talks"
                    className="rounded-md p-2 text-muted-foreground hover:bg-accent"
                >
                    <ArrowLeft className="size-4" />
                </Link>
                <h1 className="font-semibold">Talks</h1>
            </div>

            <section className="border-b px-5 py-5 sm:px-6">
                <p className="text-sm leading-relaxed text-muted-foreground">
                    Publish a talk with a title, summary, date, and a link to
                    your slides or recording. It appears on your profile and in
                    the log as a commit others can discuss.
                </p>

                <Form
                    {...TalkController.store.form()}
                    className="mt-5 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="title">Title</Label>
                                <input
                                    id="title"
                                    name="title"
                                    required
                                    maxLength={200}
                                    className={fieldClassName}
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    required
                                    maxLength={4000}
                                    rows={4}
                                    className={`${fieldClassName} min-h-24 resize-y`}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="presented_on">Date</Label>
                                    <input
                                        id="presented_on"
                                        name="presented_on"
                                        type="date"
                                        required
                                        className={fieldClassName}
                                    />
                                    <InputError message={errors.presented_on} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="link_kind">Link type</Label>
                                    <input
                                        type="hidden"
                                        name="link_kind"
                                        value={linkKind}
                                    />
                                    <Select
                                        value={linkKind}
                                        onValueChange={(value) =>
                                            setLinkKind(value as TalkLinkKind)
                                        }
                                    >
                                        <SelectTrigger
                                            id="link_kind"
                                            className="h-auto w-full py-2 text-base md:text-[15px]"
                                        >
                                            <SelectValue placeholder="Link type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="slides">
                                                Slides
                                            </SelectItem>
                                            <SelectItem value="recording">
                                                Recording
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.link_kind} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="link_url">Link URL</Label>
                                <input
                                    id="link_url"
                                    name="link_url"
                                    type="url"
                                    required
                                    placeholder="https://"
                                    maxLength={2048}
                                    className={fieldClassName}
                                />
                                <InputError message={errors.link_url} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="event_name">
                                    Event name (optional)
                                </Label>
                                <input
                                    id="event_name"
                                    name="event_name"
                                    maxLength={200}
                                    className={fieldClassName}
                                />
                                <InputError message={errors.event_name} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Publish talk
                            </Button>
                        </>
                    )}
                </Form>
            </section>

            {talks.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="fatal: no talks yet">
                        Your published talks will show up here after you publish
                        one above.
                    </EmptyFeed>
                </div>
            ) : (
                <div>
                    {talks.data.map((talk) => (
                        <TalkCard key={talk.id} talk={talk} user={user} />
                    ))}
                </div>
            )}
        </>
    );
}

Index.layout = AppLayout;
