import { Head, usePage } from '@inertiajs/react';
import { ErrorMessage, useHomeAction } from '@/components/error-message';
import { WordmarkText } from '@/components/wordmark-text';
import { home } from '@/routes';

type Status = 403 | 404 | 429 | 500 | 503;

type ErrorCopy = {
    title: string;
    command: string;
    headline: string;
    description: string;
};

function errorCopy(
    status: Status,
    path: string,
    username: string | null,
): ErrorCopy {
    switch (status) {
        case 403:
            return {
                title: 'Forbidden',
                command: username
                    ? `remote: Permission to ${path} denied to ${username}.`
                    : `remote: Permission to ${path} denied.`,
                headline: "You don't have access to this page.",
                description:
                    "It's limited to certain accounts, and yours isn't one of them.",
            };
        case 404:
            return {
                title: 'Not found',
                command: `fatal: path '${path}' does not exist in 'HEAD'`,
                headline: "There's nothing here.",
                description:
                    'The link may be mistyped, or whatever it pointed to has been deleted.',
            };
        case 429:
            return {
                title: 'Too many requests',
                command: 'error: RPC failed; HTTP 429 Too Many Requests',
                headline: 'Slow down.',
                description:
                    "You've sent too many requests in a short time. Wait a little while, then try again.",
            };
        case 500:
            return {
                title: 'Server error',
                command: 'fatal: the remote end hung up unexpectedly',
                headline: 'Something broke on our end.',
                description:
                    "It's not you. The error has been logged; try again in a moment.",
            };
        case 503:
            return {
                title: 'Down for maintenance',
                command: `fatal: unable to access '${path}': The requested URL returned error: 503`,
                headline: '/dev/null is down for maintenance.',
                description:
                    "We're making changes and will be back shortly. Try again in a few minutes.",
            };
    }
}

export default function ErrorPage({ status }: { status: Status }) {
    const page = usePage();
    const path = page.url.split('?')[0];
    const copy = errorCopy(
        status,
        path,
        page.props.auth.user?.username ?? null,
    );
    const homeAction = useHomeAction();
    const action =
        status === 503 ? { label: 'Try again', href: home() } : homeAction;

    return (
        <>
            <Head title={copy.title} />

            <main className="mx-auto flex min-h-svh w-full max-w-3xl items-center px-4 py-16 sm:px-8">
                <ErrorMessage
                    command={copy.command}
                    headline={
                        <h1>
                            <WordmarkText>{copy.headline}</WordmarkText>
                        </h1>
                    }
                    description={<p>{copy.description}</p>}
                    action={action}
                />
            </main>
        </>
    );
}
