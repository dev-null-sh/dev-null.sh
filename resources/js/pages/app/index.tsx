import { Head } from '@inertiajs/react';
import type { ReactNode, SVGAttributes } from 'react';
import { CopyableCode } from '@/components/copyable-code';
import { Button } from '@/components/ui/button';
import { WordmarkText } from '@/components/wordmark-text';
import AppLayout from '@/layouts/app-layout';
import { ANDROID_APP_URL, IOS_APP_URL } from '@/lib/mobile-apps';
import { cn } from '@/lib/utils';

const textLink =
    'underline decoration-muted-foreground/50 underline-offset-4 hover:decoration-foreground';

function AppleIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden {...props}>
            <path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701" />
        </svg>
    );
}

function AndroidIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden {...props}>
            <path d="M17.523 15.341c-.551 0-.999-.449-.999-1s.448-.999.999-.999c.551 0 .999.448.999.999s-.448 1-.999 1m-11.046 0c-.551 0-.999-.449-.999-1s.448-.999.999-.999c.551 0 .999.448.999.999s-.448 1-.999 1m11.405-6.02 1.997-3.459a.416.416 0 0 0-.152-.568.416.416 0 0 0-.568.152l-2.022 3.503C15.59 8.244 13.853 7.851 12 7.851s-3.59.393-5.137 1.098L4.841 5.447a.416.416 0 0 0-.568-.152.416.416 0 0 0-.152.568l1.997 3.459C2.689 11.187.343 14.659 0 18.761h24c-.344-4.102-2.689-7.574-6.119-9.44" />
        </svg>
    );
}

function Phone({
    className,
    children,
}: {
    className: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('@container relative', className)}>
            <span className="absolute top-[22%] -right-[0.8cqw] h-[8%] w-[1.2cqw] rounded-r-[0.6cqw] bg-linear-to-r from-[#242427] to-[#5c5c61]" />
            <span className="absolute top-[33%] -right-[0.8cqw] h-[15%] w-[1.2cqw] rounded-r-[0.6cqw] bg-linear-to-r from-[#242427] to-[#5c5c61]" />
            <div className="relative rounded-[11.5cqw] bg-linear-135 from-[#6e6e73] via-[#232326] to-[#5c5c61] p-[1cqw] shadow-[0_4cqw_10cqw_-2cqw_rgb(0_0_0/0.5)]">
                <div className="rounded-[10.5cqw] bg-black p-[2cqw]">
                    <div className="relative overflow-hidden rounded-[8.5cqw]">
                        {children}
                        <span className="absolute top-[0.9cqw] left-1/2 size-[3.4cqw] -translate-x-1/2 rounded-full bg-black shadow-[inset_0_0_0_0.7cqw_#17171a]" />
                    </div>
                </div>
            </div>
        </div>
    );
}

function Screenshot({
    name,
    alt,
    className,
}: {
    name: string;
    alt: string;
    className: string;
}) {
    return (
        <img
            src={`/images/apps/mobile-${name}.webp`}
            alt={alt}
            width={720}
            height={1280}
            className={cn('h-auto w-full', className)}
        />
    );
}

export default function Index() {
    return (
        <>
            <Head title="Apps" />

            <header className="sticky top-0 z-10 flex h-14 items-center border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">Apps</h1>
            </header>

            <article className="text-[15px] leading-relaxed">
                <section aria-labelledby="mobile-title" className="border-b">
                    <div className="px-4 pt-8 pb-6 sm:px-6 sm:pt-10">
                        <div className="flex items-center gap-4">
                            <img
                                src="/images/apps/mobile-icon.webp"
                                alt=""
                                width={256}
                                height={256}
                                className="size-16 shrink-0 rounded-[22%] border sm:size-[72px]"
                            />
                            <h2
                                id="mobile-title"
                                className="text-[1.75rem] leading-tight font-semibold tracking-tight text-balance sm:text-3xl"
                            >
                                <WordmarkText>
                                    /dev/null for iOS and Android
                                </WordmarkText>
                            </h2>
                        </div>

                        <p className="mt-4 max-w-prose text-pretty">
                            Read the log, commit from your phone, and keep up
                            with your remotes. Built by{' '}
                            <a
                                href="https://nativephp.com"
                                target="_blank"
                                rel="noopener noreferrer"
                                className={textLink}
                            >
                                NativePHP
                            </a>
                            . Want to help build?{' '}
                            <a
                                href="https://github.com/nativephp/dev-null-app"
                                target="_blank"
                                rel="noopener noreferrer"
                                className={textLink}
                            >
                                Open a pull request
                            </a>
                            .
                        </p>

                        <div className="mt-6 flex flex-wrap gap-3">
                            <Button size="lg" className="h-11" asChild>
                                <a
                                    href={IOS_APP_URL}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <AppleIcon />
                                    Join the iOS beta
                                </a>
                            </Button>
                            <Button size="lg" className="h-11" asChild>
                                <a
                                    href={ANDROID_APP_URL}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <AndroidIcon />
                                    Join the Android beta
                                </a>
                            </Button>
                        </div>
                    </div>

                    <figure className="overflow-hidden border-t bg-background bg-[radial-gradient(60%_50%_at_50%_45%,color-mix(in_oklab,var(--foreground)_10%,transparent),transparent)] pt-10 pb-12">
                        <div className="grid grid-cols-[34%_46%_34%] items-center justify-center motion-safe:animate-apps-shot-in">
                            <Phone className="-mr-[8%] [transform:perspective(1400px)_rotateY(16deg)]">
                                <Screenshot
                                    name="remote"
                                    alt="A remote in the app: a conversation with pull receipts and a HEAD marker above two new messages."
                                    className="block"
                                />
                            </Phone>
                            <Phone className="z-1">
                                <Screenshot
                                    name="log-light"
                                    alt="The log in the app: commits on the graph rail, with Following, Latest, and Trending tabs."
                                    className="dark:hidden"
                                />
                                <Screenshot
                                    name="log-dark"
                                    alt="The log in the app: commits on the graph rail, with Following, Latest, and Trending tabs."
                                    className="hidden dark:block"
                                />
                            </Phone>
                            <Phone className="-ml-[8%] [transform:perspective(1400px)_rotateY(-16deg)]">
                                <Screenshot
                                    name="profile"
                                    alt="A profile in the app: the avatar, the bio, the counts, and the commit activity."
                                    className="block"
                                />
                            </Phone>
                        </div>
                    </figure>
                </section>

                <section aria-labelledby="tui-title">
                    <div className="px-4 pt-8 pb-6 sm:px-6 sm:pt-10">
                        <div className="flex items-center gap-4">
                            <picture className="size-16 shrink-0 sm:size-[72px]">
                                <img
                                    src="/images/apps/mac-icon-light.webp"
                                    alt=""
                                    width={256}
                                    height={256}
                                    className="size-full dark:hidden"
                                />
                                <img
                                    src="/images/apps/mac-icon-dark.webp"
                                    alt=""
                                    width={256}
                                    height={256}
                                    className="hidden size-full dark:block"
                                />
                            </picture>
                            <h2
                                id="tui-title"
                                className="text-[1.75rem] leading-tight font-semibold tracking-tight text-balance sm:text-3xl"
                            >
                                <WordmarkText>
                                    /dev/null for your terminal
                                </WordmarkText>
                            </h2>
                        </div>

                        <p className="mt-4 max-w-prose text-pretty">
                            Read the log, publish commits, chat in remotes, and
                            keep up with your notifications without leaving your
                            terminal. Built by{' '}
                            <a
                                href="https://wendelladriel.com"
                                target="_blank"
                                rel="noopener noreferrer"
                                className={textLink}
                            >
                                Wendell Adriel
                            </a>
                            .{' '}
                            <a
                                href="https://github.com/WendellAdriel/dev-null-tui"
                                target="_blank"
                                rel="noopener noreferrer"
                                className={textLink}
                            >
                                Check the GitHub repository
                            </a>
                            .
                        </p>

                        <div className="mt-6 space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Install on Linux, macOS, or Windows with WSL:
                            </p>
                            <CopyableCode
                                code="curl -fsSL https://raw.githubusercontent.com/WendellAdriel/dev-null-tui/main/scripts/install.sh | sh"
                                label="install command"
                            />
                            <p className="text-sm text-pretty text-muted-foreground">
                                On Windows without WSL, follow the{' '}
                                <a
                                    href="https://github.com/WendellAdriel/dev-null-tui/blob/main/docs/installation.md#windows-powershell"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={textLink}
                                >
                                    Windows installation instructions
                                </a>
                                .
                            </p>
                        </div>
                    </div>

                    <figure className="px-4 pb-8 sm:px-6">
                        <img
                            src="/images/apps/tui-light.webp"
                            alt="/dev/null TUI with the Following log, commit threads, keyboard shortcuts, and a profile in the terminal."
                            width={2483}
                            height={1486}
                            loading="lazy"
                            className="h-auto w-full dark:hidden"
                        />
                        <img
                            src="/images/apps/tui-dark.webp"
                            alt="/dev/null TUI with the Following log, commit threads, keyboard shortcuts, and a profile in the terminal."
                            width={2483}
                            height={1486}
                            loading="lazy"
                            className="hidden h-auto w-full dark:block"
                        />
                    </figure>
                </section>
            </article>
        </>
    );
}

Index.layout = AppLayout;
