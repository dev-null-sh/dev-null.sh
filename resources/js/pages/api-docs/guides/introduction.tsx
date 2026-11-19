import { Link } from '@inertiajs/react';
import OpenApiController from '@/actions/App/Http/Controllers/Api/OpenApiController';
import ApiDocsController from '@/actions/App/Http/Controllers/ApiDocsController';
import {
    Code,
    FieldList,
    Guide,
    GuideSection,
    Snippet,
} from '@/components/api-docs/guide';
import type { ApiDocsSnippet } from '@/types';

const API_URL = 'https://dev-null.sh/api/v1';

export function Introduction({
    snippets,
}: {
    snippets: Record<string, ApiDocsSnippet>;
}) {
    return (
        <Guide>
            <GuideSection title="What it is">
                <p>
                    The JSON API the /dev/null apps use, open to anything you
                    build: a bot, a bridge, a widget on your own site.
                </p>
                <p>
                    Reading public profiles and commits needs nothing. Acting
                    for someone needs their approval and a signature on every
                    request: there are no passwords and no API tokens.
                </p>
                <FieldList
                    fields={[
                        { name: 'base url', children: <Code>{API_URL}</Code> },
                        {
                            name: 'format',
                            children:
                                'JSON, or multipart when a request carries images or a voice note',
                        },
                        {
                            name: 'accept',
                            children: (
                                <>
                                    <Code>Accept: application/json</Code> on
                                    every request, so every error comes back as
                                    JSON
                                </>
                            ),
                        },
                        {
                            name: 'spec',
                            children: (
                                <>
                                    <a href={OpenApiController.show().url}>
                                        OpenAPI 3.1
                                    </a>
                                    , generated from the code, so it changes
                                    when the API does
                                </>
                            ),
                        },
                    ]}
                />
            </GuideSection>

            <GuideSection title="Your first request">
                <p>A profile, as anyone can read it:</p>
                <Snippet snippet={snippets.firstRequest} />
                <p>
                    Lists come a page at a time: see{' '}
                    <Link href={ApiDocsController.show('pagination')} prefetch>
                        Pagination
                    </Link>
                    . To read what someone sees, or to commit for them, sign the
                    request: see{' '}
                    <Link
                        href={ApiDocsController.show('authentication')}
                        prefetch
                    >
                        Authentication
                    </Link>
                    .
                </p>
            </GuideSection>

            <GuideSection title="Same rules as the web">
                <p>
                    A signed request sees what the web shows the person who
                    signed it, and goes through the same validation, moderation
                    and limits: a commit may come back pending, and a blocked
                    profile stays out of reach.
                </p>
            </GuideSection>
        </Guide>
    );
}
