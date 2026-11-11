import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import HumanVerificationController from '@/actions/App/Http/Controllers/HumanVerificationController';
import { ErrorMessage } from '@/components/error-message';
import { HumanVerification } from '@/components/human-verification';

export default function HumanVerificationShow() {
    const { errors } = usePage().props;
    const [attempt, setAttempt] = useState(0);

    const submit = (token: string) => {
        if (token === '') {
            return;
        }

        router.post(
            HumanVerificationController.store.url(),
            { token },
            { onError: () => setAttempt((count) => count + 1) },
        );
    };

    return (
        <>
            <Head title="One last check" />

            <main className="mx-auto flex min-h-svh w-full max-w-3xl items-center px-4 py-16 sm:px-8">
                <ErrorMessage
                    command="git init --human"
                    headline={<h1>One last check.</h1>}
                    description={
                        <p>
                            Confirm that you are a person, and your account is
                            created. Cloudflare runs the check.
                        </p>
                    }
                >
                    <div className="mt-8 grid max-w-sm gap-2">
                        <HumanVerification key={attempt} onToken={submit} />
                        <p
                            className="min-h-5 text-sm text-destructive"
                            role="alert"
                            data-test="human-verification-error"
                        >
                            {errors.token}
                        </p>
                    </div>
                </ErrorMessage>
            </main>
        </>
    );
}
