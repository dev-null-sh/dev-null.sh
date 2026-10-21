import { Head, router } from '@inertiajs/react';
import ExploreReportController from '@/actions/App/Http/Controllers/ExploreReportController';
import { ModerationTabs } from '@/components/moderation-tabs';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { ExploreReasons } from '@/types/explore';

export default function Explore({
    reports,
    suppressed,
    reasons,
}: {
    reports: {
        topic_id: string;
        title: string;
        kind: string;
        reason: string;
        created_at: string;
    }[];
    suppressed: { id: string; title: string }[];
    reasons: ExploreReasons;
}) {
    return (
        <>
            <Head title="Explore reports" />
            <header className="border-b px-4 py-4 sm:px-6">
                <h1 className="text-lg font-semibold">Moderation</h1>
            </header>
            <div className="overflow-x-auto [&_nav]:min-w-[32rem]">
                <ModerationTabs active="explore" />
            </div>
            <section aria-label="Explore reports">
                <h2 className="border-b px-4 py-3 font-semibold sm:px-6">
                    Explore reports
                </h2>
                {reports.length === 0 && (
                    <p className="px-4 py-8 text-sm text-muted-foreground">
                        No unresolved Explore reports.
                    </p>
                )}
                {reports.map((report, index) => (
                    <article
                        key={`${report.topic_id}-${index}`}
                        className="space-y-3 border-b px-4 py-4 sm:px-6"
                    >
                        <h3 className="font-medium break-words">
                            {report.title}
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            {reasons[report.reason]}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.put(
                                        ExploreReportController.update(
                                            report.topic_id,
                                        ),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Dismiss reports
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    router.put(
                                        ExploreReportController.update(
                                            report.topic_id,
                                        ),
                                        { suppressed: true },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Suppress topic
                            </Button>
                        </div>
                    </article>
                ))}
            </section>
            <section
                aria-label="Suppressed topics"
                className="px-4 py-6 sm:px-6"
            >
                <h2 className="font-semibold">Suppressed topics</h2>
                {suppressed.map((topic) => (
                    <div
                        key={topic.id}
                        className="flex min-h-16 items-center gap-3 border-b py-3"
                    >
                        <span
                            className="min-w-0 flex-1 truncate"
                            title={topic.title}
                        >
                            {topic.title}
                        </span>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.put(
                                    ExploreReportController.update(topic.id),
                                    { suppressed: false },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Restore
                        </Button>
                    </div>
                ))}
            </section>
        </>
    );
}
Explore.layout = AppLayout;
