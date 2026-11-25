import {
    Code,
    Guide,
    GuideSection,
    Snippet,
} from '@/components/api-docs/guide';
import type { ApiDocsSnippet } from '@/types';

export function Pagination({
    snippets,
}: {
    snippets: Record<string, ApiDocsSnippet>;
}) {
    return (
        <Guide>
            <GuideSection title="A page at a time">
                <p>
                    Lists are cursor-paginated, newest first, 20 a page. A
                    cursor marks where the page ended, so commits arriving
                    meanwhile never push the next page back.
                </p>
                <Snippet snippet={snippets.page} />
                <p>
                    Follow <Code>links.next</Code> until it is <Code>null</Code>
                    , or pass <Code>meta.next_cursor</Code> as{' '}
                    <Code>cursor</Code> yourself. Keep the other parameters of
                    the first request: the links already do.
                </p>
            </GuideSection>

            <GuideSection title="Lists that page differently">
                <ul className="list-disc space-y-1.5 pl-5">
                    <li>
                        Followers, following and followed organizations come 30
                        a page.
                    </li>
                    <li>
                        A commit's replies come 50 a page, oldest first, or
                        newest first with <Code>newest=1</Code>.
                    </li>
                    <li>
                        A log ranked by something else than time keeps its
                        rank's order. Asked to reach back to a commit you
                        already have, a log or the notifications send every one
                        since, so a page can hold more than 20.
                    </li>
                    <li>
                        Scheduled commits come by page number, 20 a page, drafts
                        not yet submitted first, then the soonest:{' '}
                        <Code>links.next</Code> works the same, and{' '}
                        <Code>meta</Code> also has <Code>current_page</Code>,{' '}
                        <Code>last_page</Code> and <Code>total</Code>.
                    </li>
                </ul>
            </GuideSection>
        </Guide>
    );
}
