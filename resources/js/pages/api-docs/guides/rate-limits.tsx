import { Code, Guide, GuideSection } from '@/components/api-docs/guide';
import type { ApiRateLimit } from '@/types';

export function RateLimits({ limits }: { limits: ApiRateLimit[] }) {
    return (
        <Guide>
            <GuideSection title="How many">
                <p>
                    Every request counts toward one of the first two: unsigned
                    requests by address, signed ones by the person who signed.
                    Some actions have a budget of their own on top, the same as
                    on the web.
                </p>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="text-left font-mono text-[13px] text-muted-foreground">
                            <tr className="border-b">
                                <th className="px-3 py-2 font-normal">what</th>
                                <th className="px-3 py-2 text-right font-normal">
                                    limit
                                </th>
                                <th className="px-3 py-2 font-normal">per</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {limits.map((row) => (
                                <tr key={`${row.what} ${row.per}`}>
                                    <td className="px-3 py-2">{row.what}</td>
                                    <td className="px-3 py-2 text-right font-mono text-[13px] tabular-nums">
                                        {row.limit}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {row.per}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </GuideSection>

            <GuideSection title="Past the limit">
                <p>
                    Every answer says where you are in the first two in{' '}
                    <Code>X-RateLimit-Limit</Code> and{' '}
                    <Code>X-RateLimit-Remaining</Code>; an action's own budget
                    isn't in them. Past any limit, the answer is a{' '}
                    <Code>429</Code> with <Code>Retry-After</Code>, the seconds
                    to wait, and a <Code>message</Code>.
                </p>
                <p>
                    A signed request also gets the code{' '}
                    <Code>rate_limited</Code>, except past the pings and commit
                    searches budgets, which only say it in their message. An
                    unsigned request never gets a code.
                </p>
            </GuideSection>
        </Guide>
    );
}
