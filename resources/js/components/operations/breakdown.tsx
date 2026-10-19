import type { ReactNode } from 'react';
import { formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import type { BreakdownRow } from '@/types';

/**
 * A ranked list with a bar behind each name, sized from the largest row. Empty names print the given word, muted;
 * the others print as formatted, or as rendered when a row needs more than text, a link say.
 */
export function Breakdown({
    title,
    unit,
    empty,
    rows,
    formatName = (name) => name,
    renderName,
}: {
    title: string;
    unit: string;
    empty: string;
    rows: BreakdownRow[];
    formatName?: (name: string) => string;
    renderName?: (name: string) => ReactNode;
}) {
    const top = Math.max(0, ...rows.map((row) => row.count));

    return (
        <div className="rounded-lg border px-4">
            <table className="w-full table-fixed text-sm">
                <thead>
                    <tr className="font-mono text-[13px] text-muted-foreground">
                        <th
                            scope="col"
                            className="py-2.5 text-left font-normal"
                        >
                            {title}
                        </th>
                        <th
                            scope="col"
                            className="w-20 py-2.5 text-right font-normal"
                        >
                            {unit}
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y border-t">
                    {rows.length === 0 && (
                        <tr>
                            <td
                                colSpan={2}
                                className="py-2.5 font-mono text-[13px] text-muted-foreground"
                            >
                                nothing yet
                            </td>
                        </tr>
                    )}
                    {rows.map((row) => {
                        const name =
                            row.name === '' ? empty : formatName(row.name);

                        return (
                            <tr key={row.name}>
                                <th
                                    scope="row"
                                    className="relative py-2.5 pr-3 text-left font-mono text-[13px] font-normal"
                                >
                                    <span
                                        aria-hidden
                                        className="absolute inset-y-1.5 left-0 -ml-2 rounded bg-muted"
                                        style={{
                                            width: `calc(${top > 0 ? (row.count / top) * 100 : 0}% + 0.5rem)`,
                                        }}
                                    />
                                    <span
                                        className={cn(
                                            'relative block truncate',
                                            row.name === '' &&
                                                'text-muted-foreground',
                                        )}
                                        title={name}
                                    >
                                        {renderName && row.name !== ''
                                            ? renderName(row.name)
                                            : name}
                                    </span>
                                </th>
                                <td className="py-2.5 text-right font-mono text-[13px] font-medium tabular-nums">
                                    {formatCount(row.count)}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
