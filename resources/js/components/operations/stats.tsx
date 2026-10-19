/**
 * A row of stat tiles: a mono label over a mono value, with an optional muted note under it.
 */
export function Stats({
    stats,
}: {
    stats: { label: string; value: string; note?: string }[];
}) {
    return (
        <dl className="grid grid-cols-2 divide-x divide-y rounded-lg border text-sm sm:grid-cols-4 sm:divide-y-0">
            {stats.map((stat) => (
                <div key={stat.label} className="px-4 py-2.5">
                    <dt className="font-mono text-[13px] text-muted-foreground">
                        {stat.label}
                    </dt>
                    <dd className="font-mono text-base font-medium tabular-nums">
                        {stat.value}
                    </dd>
                    {stat.note && (
                        <dd className="font-mono text-[13px] text-muted-foreground tabular-nums">
                            {stat.note}
                        </dd>
                    )}
                </div>
            ))}
        </dl>
    );
}
