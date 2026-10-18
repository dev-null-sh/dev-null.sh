import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Presentation } from 'lucide-react';
import TalkController from '@/actions/App/Http/Controllers/TalkController';
import InputError from '@/components/input-error';
import { TalkCard } from '@/components/talk-card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useUser } from '@/hooks/use-user';
import AppLayout from '@/layouts/app-layout';
import type { TalkSummary } from '@/types';

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
                                    <select
                                        id="link_kind"
                                        name="link_kind"
                                        required
                                        defaultValue="slides"
                                        className={fieldClassName}
                                    >
                                        <option value="slides">Slides</option>
                                        <option value="recording">
                                            Recording
                                        </option>
                                    </select>
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
                <div className="space-y-3 px-6 py-14 text-center">
                    <Presentation className="mx-auto size-6 text-muted-foreground" />
                    <p className="font-medium">No talks yet</p>
                    <p className="text-sm text-muted-foreground">
                        Your published talks will show up here.
                    </p>
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
