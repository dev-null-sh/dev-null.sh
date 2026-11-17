import { usePage } from '@inertiajs/react';

export default function NewYearSnippet() {
    const { eventTheme, eventYear } = usePage().props;

    if (eventTheme !== 'newyear') {
        return null;
    }

    return (
        <section
            aria-label="New Year greeting"
            className="rounded-lg border bg-card p-4"
        >
            <pre className="overflow-x-auto font-mono text-[13px] leading-relaxed text-card-foreground">
                <code>
                    <span className="text-muted-foreground">
                        {'// the year is over'}
                    </span>
                    {'\nconsole.'}
                    <span className="text-primary">log</span>
                    {'('}
                    <span className="text-success">
                        {`"Happy ${eventYear}!"`}
                    </span>
                    {');'}
                </code>
            </pre>
        </section>
    );
}
