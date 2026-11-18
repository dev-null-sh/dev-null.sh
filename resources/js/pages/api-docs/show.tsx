import { Head, usePage } from '@inertiajs/react';
import ApiDocsLayout from '@/layouts/api-docs/layout';
import { Introduction } from '@/pages/api-docs/guides/introduction';
import type { ApiDocsProps } from '@/types';

export default function Show() {
    const { sections, current, snippets } = usePage<ApiDocsProps>().props;
    const section = sections.find((candidate) => candidate.slug === current);

    return (
        <>
            <Head title={`${section?.title} - API docs`} />

            {current === 'introduction' && <Introduction snippets={snippets} />}
        </>
    );
}

Show.layout = ApiDocsLayout;
