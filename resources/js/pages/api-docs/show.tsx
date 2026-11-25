import { Head, usePage } from '@inertiajs/react';
import ApiDocsLayout from '@/layouts/api-docs/layout';
import { Authentication } from '@/pages/api-docs/guides/authentication';
import { Errors } from '@/pages/api-docs/guides/errors';
import { Introduction } from '@/pages/api-docs/guides/introduction';
import { Pagination } from '@/pages/api-docs/guides/pagination';
import { RateLimits } from '@/pages/api-docs/guides/rate-limits';
import type { ApiDocsProps } from '@/types';

export default function Show() {
    const { sections, current, errorCodes, rateLimits, signing, snippets } =
        usePage<ApiDocsProps>().props;
    const section = sections.find((candidate) => candidate.slug === current);

    return (
        <>
            <Head title={`${section?.title} - API docs`} />

            {current === 'introduction' && <Introduction snippets={snippets} />}
            {current === 'authentication' && (
                <Authentication signing={signing} snippets={snippets} />
            )}
            {current === 'rate-limits' && <RateLimits limits={rateLimits} />}
            {current === 'pagination' && <Pagination snippets={snippets} />}
            {current === 'errors' && (
                <Errors codes={errorCodes} snippets={snippets} />
            )}
        </>
    );
}

Show.layout = ApiDocsLayout;
