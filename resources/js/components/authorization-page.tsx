import { Head } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import AppLogo from '@/components/app-logo';

/**
 * The standalone page a sign-in approval is asked on: the logo, then the command line the step reads as, then the
 * step itself, laid out as two commits on a lane.
 */
export function AuthorizationPage({
    title,
    lane,
    command,
    children,
}: {
    title: string;
    lane: string;
    command: string;
    children: ReactNode;
}) {
    return (
        <>
            <Head title={title} />

            <main
                className="mx-auto flex min-h-svh w-full max-w-3xl items-center px-4 py-16 sm:px-8"
                style={{ '--lane': lane } as CSSProperties}
            >
                <ol className="w-full">
                    <li className="flex">
                        <div
                            aria-hidden
                            className="relative w-10 shrink-0 sm:w-16"
                        >
                            <span className="absolute top-3.5 bottom-6 left-1/2 w-0.5 -translate-x-1/2 bg-(--lane) mask-b-from-40%" />
                            <span className="absolute top-3.5 left-1/2 size-3.5 -translate-1/2 rounded-full border-[3px] border-background bg-(--lane) shadow-[0_0_0_2px_var(--lane)]" />
                        </div>
                        <div className="min-w-0 flex-1 pb-14 sm:pb-16">
                            <AppLogo />
                        </div>
                    </li>

                    <li className="flex">
                        <div
                            aria-hidden
                            className="relative w-10 shrink-0 sm:w-16"
                        >
                            <span className="absolute top-2.5 left-1/2 size-3.5 -translate-1/2 rounded-full border-2 border-muted-foreground bg-background" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="font-mono text-[13px] [overflow-wrap:anywhere] text-muted-foreground">
                                {command}
                            </p>
                            {children}
                        </div>
                    </li>
                </ol>
            </main>
        </>
    );
}
