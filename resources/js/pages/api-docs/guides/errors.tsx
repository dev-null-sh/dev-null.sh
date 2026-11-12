import {
    Code,
    Guide,
    GuideSection,
    Snippet,
} from '@/components/api-docs/guide';
import type { ApiDocsSnippet, ApiErrorCodeStatus } from '@/types';

export function Errors({
    codes,
    snippets,
}: {
    codes: ApiErrorCodeStatus[];
    snippets: Record<string, ApiDocsSnippet>;
}) {
    return (
        <Guide>
            <GuideSection title="What went wrong">
                <p>
                    Every error says what happened in <Code>message</Code>, in
                    the web's own words. An error the API answers itself, listed
                    below, also says which one it is in <Code>code</Code>. A
                    code never changes once released: match on it, and show the
                    message.
                </p>
                <p>
                    Laravel's own errors have no <Code>code</Code>: a{' '}
                    <Code>404</Code> for something that doesn't exist or isn't
                    yours to see, a <Code>403</Code> for an action you can't
                    take, and validation errors, which also say what is wrong
                    with each field in <Code>errors</Code>, below.
                </p>
                <Snippet snippet={snippets.error} />
            </GuideSection>

            <GuideSection title="Codes" meta={`${codes.length} codes`}>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="text-left font-mono text-[13px] text-muted-foreground">
                            <tr className="border-b">
                                <th className="px-3 py-2 font-normal">
                                    status
                                </th>
                                <th className="px-3 py-2 font-normal">code</th>
                                <th className="px-3 py-2 font-normal">
                                    meaning
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {codes.map(({ code, status, meaning }) => (
                                <tr key={code}>
                                    <td className="px-3 py-2 font-mono text-[13px] tabular-nums">
                                        {status}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-[13px] whitespace-nowrap">
                                        {code}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {meaning}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </GuideSection>

            <GuideSection title="Validation">
                <p>
                    A request that doesn't pass validation is a <Code>422</Code>
                    , with what is wrong with each field, as Laravel answers it:
                </p>
                <Snippet snippet={snippets.validation} />
            </GuideSection>
        </Guide>
    );
}
