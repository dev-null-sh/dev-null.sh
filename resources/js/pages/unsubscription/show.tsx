import { Form, Head } from '@inertiajs/react';
import { ErrorMessage, useHomeAction } from '@/components/error-message';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * Where the unsubscribe link in an email lands. It only asks, since mail scanners open every link before people do.
 */
export default function Unsubscription({
    url,
    unsubscribed,
}: {
    url: string;
    unsubscribed: boolean;
}) {
    const home = useHomeAction();

    return (
        <>
            <Head title="Unsubscribe" />

            <main className="mx-auto flex min-h-svh w-full max-w-3xl items-center px-4 py-16 sm:px-8">
                <ErrorMessage
                    command={
                        unsubscribed
                            ? 'emails > /dev/null'
                            : 'emails > /dev/null?'
                    }
                    headline={
                        <h1>
                            {unsubscribed
                                ? "You're unsubscribed."
                                : 'Unsubscribe from /dev/null emails?'}
                        </h1>
                    }
                    description={
                        <p>
                            {unsubscribed
                                ? "We won't email you again. Your account and everything on it stay as they are."
                                : 'Every now and then, the people running /dev/null email everyone about what changed. Unsubscribe and you won’t get them anymore.'}
                        </p>
                    }
                    action={unsubscribed ? home : undefined}
                >
                    {unsubscribed ? undefined : (
                        <Form action={url} method="post" className="mt-8">
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={processing}
                                    data-test="unsubscribe-button"
                                >
                                    {processing && <Spinner />}
                                    Unsubscribe
                                </Button>
                            )}
                        </Form>
                    )}
                </ErrorMessage>
            </main>
        </>
    );
}
