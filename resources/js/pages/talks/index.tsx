import { Form, Head, InfiniteScroll } from '@inertiajs/react';
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
import AppLayout from '@/layouts/app-layout';
import type { CursorPaginated, TalkSummary, TalkVisibility } from '@/types';

const fieldClassName =
    'block w-full rounded-md border bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50 md:text-[15px]';

type ExplorerTalk = TalkSummary & {
    user: {
        id: string;
        username: string;
        name: string;
        avatar_url: string | null;
        lane: number | string;
    };
};

export default function Index({ talks }: { talks: CursorPaginated<ExplorerTalk> }) {
    const [isPublishing, setIsPublishing] = useState(false);
    const [visibility, setVisibility] = useState<TalkVisibility>('public');

    return (
        <>
            <Head title="Talks" />
            <div className="flex h-14 items-center justify-between gap-3 border-b px-4">
                <h1 className="font-semibold">Talks</h1>
                <Button
                    type="button"
                    size="sm"
                    variant={isPublishing ? 'outline' : 'default'}
                    onClick={() => setIsPublishing((open) => !open)}
                >
                    {isPublishing ? 'Cancel' : 'New talk'}
                </Button>
            </div>

            {isPublishing && (
                <section className="border-b px-5 py-5 sm:px-6">
                    <p className="text-sm leading-relaxed text-muted-foreground">
                        Add a talk with a recording, slides, or both. Save a
                        private draft while you prepare, or publish when it is
                        ready to explore.
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
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
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
                                        <Label htmlFor="presented_on">
                                            Date
                                        </Label>
                                        <input
                                            id="presented_on"
                                            name="presented_on"
                                            type="date"
                                            required
                                            className={fieldClassName}
                                        />
                                        <InputError
                                            message={errors.presented_on}
                                        />
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
                                        <InputError
                                            message={errors.event_name}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="recording_url">
                                        Recording URL
                                    </Label>
                                    <input
                                        id="recording_url"
                                        name="recording_url"
                                        type="url"
                                        placeholder="https://youtube.com/…"
                                        maxLength={2048}
                                        className={fieldClassName}
                                    />
                                    <InputError message={errors.recording_url} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="slides_url">
                                        Slides URL
                                    </Label>
                                    <input
                                        id="slides_url"
                                        name="slides_url"
                                        type="url"
                                        placeholder="https://speakerdeck.com/…"
                                        maxLength={2048}
                                        className={fieldClassName}
                                    />
                                    <InputError message={errors.slides_url} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="visibility">
                                        Visibility
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="visibility"
                                        value={visibility}
                                    />
                                    <Select
                                        value={visibility}
                                        onValueChange={(value) =>
                                            setVisibility(
                                                value as TalkVisibility,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="visibility"
                                            className="h-auto w-full py-2 text-base md:text-[15px]"
                                        >
                                            <SelectValue placeholder="Visibility" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="public">
                                                Public — explore and profile
                                            </SelectItem>
                                            <SelectItem value="unlisted">
                                                Unlisted — link only
                                            </SelectItem>
                                            <SelectItem value="private">
                                                Private draft — only you
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.visibility} />
                                </div>

                                <Button type="submit" disabled={processing}>
                                    {visibility === 'private'
                                        ? 'Save draft'
                                        : 'Publish talk'}
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            )}

            {talks.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="fatal: no talks yet">
                        Explore talks with recordings and slides. Add one to get
                        started.
                    </EmptyFeed>
                </div>
            ) : (
                <InfiniteScroll
                    data="talks"
                    onlyNext
                    preserveUrl
                    loading={
                        <div
                            role="status"
                            aria-label="Loading more talks"
                            className="px-5 py-6 text-sm text-muted-foreground sm:px-6"
                        >
                            Loading…
                        </div>
                    }
                >
                    {talks.data.map((talk) => (
                        <TalkCard key={talk.id} talk={talk} user={talk.user} />
                    ))}
                </InfiniteScroll>
            )}
        </>
    );
}

Index.layout = AppLayout;
