import { Placeholder } from '@/components/operations/placeholder';

/**
 * A row of stat tiles: a mono label over a mono value, with an optional muted note under it. Four across when there
 * is room for them, two by two otherwise, so labels and notes keep to their lines. A stat without a value yet holds its
 * place with a placeholder.
 */
export function Stats({
    stats,
}: {
    stats: { label: string; value?: string; note?: string }[];
}) {
    return (
        <div className="@container">
            <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-lg border bg-border text-sm @2xl:grid-cols-4">
                {stats.map((stat) => (
                    <div key={stat.label} className="bg-card px-4 py-2.5">
                        <dt className="font-mono text-[13px] text-muted-foreground">
                            {stat.label}
                        </dt>
                        <dd className="font-mono text-base font-medium tabular-nums">
                            {stat.value ?? <Placeholder className="w-[6ch]" />}
                        </dd>
                        {stat.note && (
                            <dd className="font-mono text-[13px] text-muted-foreground tabular-nums">
                                {stat.note}
                            </dd>
                        )}
                    </div>
                ))}
            </dl>
        </div>
    );
}
