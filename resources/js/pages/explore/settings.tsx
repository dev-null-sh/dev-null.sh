import { Head, Link, useForm } from '@inertiajs/react';
import ExploreController from '@/actions/App/Http/Controllers/ExploreController';
import ExploreHiddenTopicController from '@/actions/App/Http/Controllers/ExploreHiddenTopicController';
import ExplorePreferenceController from '@/actions/App/Http/Controllers/ExplorePreferenceController';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

export default function Settings({
    personalized,
    hidden,
}: {
    personalized: boolean;
    hidden: { id: string; title: string; kind: string }[];
}) {
    const form = useForm({ personalized });
    return (
        <>
            <Head title="Explore settings" />
            <header className="border-b px-4 py-4 sm:px-6">
                <Link
                    href={ExploreController.index()}
                    className="text-sm text-primary hover:underline"
                >
                    ← Explore
                </Link>
                <h1 className="mt-3 text-lg font-semibold">Explore settings</h1>
            </header>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(ExplorePreferenceController.update().url, {
                        preserveScroll: true,
                    });
                }}
                className="space-y-4 border-b px-4 py-6 sm:px-6"
            >
                <label className="flex items-start gap-3">
                    <input
                        type="checkbox"
                        checked={form.data.personalized}
                        onChange={(event) =>
                            form.setData('personalized', event.target.checked)
                        }
                        className="mt-1 size-4 accent-primary"
                    />
                    <span>
                        <span className="block font-medium">
                            Personalize Explore
                        </span>
                        <span className="mt-1 block text-sm text-muted-foreground">
                            Use developers you follow and subjects you star to
                            recommend discussions. Trending and category
                            rankings stay platform-wide.
                        </span>
                    </span>
                </label>
                <Button
                    type="submit"
                    disabled={form.processing}
                    className="min-w-28"
                >
                    {form.processing ? 'Saving…' : 'Save settings'}
                </Button>
                <p className="min-h-5 text-sm" role="status">
                    {form.errors.personalized ??
                        (form.recentlySuccessful ? 'Settings saved.' : '')}
                </p>
            </form>
            <section className="px-4 py-6 sm:px-6" aria-label="Hidden topics">
                <h2 className="font-semibold">Hidden subjects and stories</h2>
                {hidden.length === 0 ? (
                    <p className="mt-3 text-sm text-muted-foreground">
                        You have no hidden topics.
                    </p>
                ) : (
                    <ul className="mt-3 divide-y">
                        {hidden.map((topic) => (
                            <li
                                key={topic.id}
                                className="flex min-h-16 items-center gap-4 py-3"
                            >
                                <span
                                    className="min-w-0 flex-1 truncate"
                                    title={topic.title}
                                >
                                    {topic.kind === 'subject' ? '#' : ''}
                                    {topic.title}
                                </span>
                                <Link
                                    href={ExploreHiddenTopicController.destroy(
                                        topic.id,
                                    )}
                                    as="button"
                                    preserveScroll
                                    className="min-w-20 rounded-md border px-3 py-2 text-sm hover:bg-accent"
                                >
                                    Restore
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}
Settings.layout = AppLayout;
