import { Head, Link } from '@inertiajs/react';
import {
    Check,
    Command,
    Copy,
    Download,
    EyeOff,
    Mic,
    Users,
} from 'lucide-react';
import { useEffect, useState, useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import DeviceController from '@/actions/App/Http/Controllers/DeviceController';
import PrivacyController from '@/actions/App/Http/Controllers/PrivacyController';
import { Button } from '@/components/ui/button';
import { WordmarkText } from '@/components/wordmark-text';
import { useClipboard } from '@/hooks/use-clipboard';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';

type Release = {
    version: string;
    build: number;
    revision: string;
    released_at: string;
    minimum_system_version: string;
    notarized: boolean;
    size: number;
    sha256: string;
    download_url: string;
    url: string;
};

const features = [
    {
        icon: Command,
        title: 'Native and fast',
        description:
            'A SwiftUI app for Apple silicon and Intel that opens instantly. ⌘K finds anyone, ⌥↑ and ⌥↓ move between remotes.',
    },
    {
        icon: Users,
        title: 'Remotes and orgs',
        description:
            'One-to-one remotes and orgs of maintainers and contributors, with fetched requests you merge or drop, and pull receipts when you both share them.',
    },
    {
        icon: Mic,
        title: 'Voice notes and reactions',
        description:
            'Record a voice note of up to a minute, react with any emoji, reply to a message, and paste or drop images.',
    },
    {
        icon: EyeOff,
        title: 'Private banners',
        description:
            'Notifications say who pushed, never what they said. Reply or mark as pulled right from the banner.',
    },
];

/** macOS counts in decimal megabytes, so the size matches what Finder shows. */
function megabytes(bytes: number) {
    return `${(bytes / 1_000_000).toFixed(1)} MB`;
}

/** In UTC, so the server and the browser print the same day. */
function releaseDate(value: string) {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(value));
}

/**
 * iPads asking for desktop sites send a Mac's user agent, which the server takes at its word; touch gives them away.
 */
function useIsMac(isMacAgent: boolean) {
    const hasTouch = useSyncExternalStore(
        noChanges,
        () => navigator.maxTouchPoints > 1,
        () => false,
    );

    return isMacAgent && !hasTouch;
}

function noChanges() {
    return () => {};
}

export default function Index({
    release,
    isMac: isMacAgent,
}: {
    release: Release | null;
    isMac: boolean;
}) {
    const isMac = useIsMac(isMacAgent);

    return (
        <>
            <Head title="Apps" />

            <header className="sticky top-0 z-10 flex h-14 items-center justify-between gap-3 border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">Apps</h1>
                <span className="font-mono text-[13px] text-muted-foreground">
                    macOS
                </span>
            </header>

            <article className="text-[15px] leading-relaxed">
                <section
                    id="mac"
                    aria-labelledby="mac-title"
                    className="scroll-mt-14 px-4 pt-8 pb-6 sm:px-6 sm:pt-10"
                >
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
                        <div className="min-w-0">
                            <p className="font-mono text-[13px] text-muted-foreground">
                                {release === null
                                    ? 'DevNull.app'
                                    : `v${release.version} · build ${release.build}`}
                            </p>
                            <h2
                                id="mac-title"
                                className="text-[1.75rem] leading-tight font-semibold tracking-tight text-balance sm:text-3xl"
                            >
                                <WordmarkText>/dev/null for Mac</WordmarkText>
                            </h2>
                        </div>
                    </div>

                    <p className="mt-4 max-w-prose text-pretty">
                        Your remotes and orgs in a native Mac app. Push, pull,
                        and react with everyone on{' '}
                        <WordmarkText>/dev/null</WordmarkText> without keeping a
                        tab open.
                    </p>

                    <DownloadPanel release={release} isMac={isMac} />
                </section>

                <figure className="px-4 pb-8 sm:px-6">
                    <div className="motion-safe:animate-apps-shot-in">
                        <img
                            src="/images/apps/mac-light.webp"
                            alt="/dev/null for Mac with an org open: a link card, emoji reactions, a voice note, and who has pulled it, beside the list of remotes."
                            width={1400}
                            height={901}
                            fetchPriority="high"
                            className="h-auto w-full dark:hidden"
                        />
                        <img
                            src="/images/apps/mac-dark.webp"
                            alt="/dev/null for Mac with an org open: a link card, emoji reactions, a voice note, and who has pulled it, beside the list of remotes."
                            width={1400}
                            height={901}
                            loading="lazy"
                            className="hidden h-auto w-full dark:block"
                        />
                    </div>
                    <figcaption className="mt-2 text-center font-mono text-xs text-muted-foreground">
                        Made-up people, real app.
                    </figcaption>
                </figure>

                <section
                    aria-labelledby="features-title"
                    className="border-t px-4 py-6 sm:px-6"
                >
                    <h2 id="features-title" className="sr-only">
                        Features
                    </h2>
                    <ul className="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                        {features.map(({ icon: Icon, title, description }) => (
                            <li key={title}>
                                <h3 className="flex items-center gap-2 font-semibold">
                                    <Icon
                                        aria-hidden
                                        className="size-4 text-muted-foreground"
                                    />
                                    {title}
                                </h3>
                                <p className="mt-1 text-sm text-pretty text-muted-foreground">
                                    {description}
                                </p>
                            </li>
                        ))}
                    </ul>
                </section>

                <section
                    id="install"
                    aria-labelledby="install-title"
                    className="scroll-mt-14 border-t px-4 py-6 sm:px-6"
                >
                    <h2
                        id="install-title"
                        className="text-lg font-semibold tracking-tight"
                    >
                        Install
                    </h2>
                    <ol className="mt-4">
                        <InstallStep index={0} command="open DevNull.dmg">
                            Open the download and drag{' '}
                            <WordmarkText>/dev/null</WordmarkText> onto
                            Applications.
                        </InstallStep>
                        <InstallStep index={1} command="open -a DevNull">
                            Open it from Applications.
                            {release !== null && !release.notarized && (
                                <>
                                    {' '}
                                    The first time, macOS asks you to approve
                                    it: see{' '}
                                    <a
                                        href="#first-open"
                                        className="underline decoration-rail underline-offset-4 hover:decoration-current"
                                    >
                                        if macOS blocks it
                                    </a>
                                    .
                                </>
                            )}
                        </InstallStep>
                        <InstallStep index={2} command="git remote add github">
                            Choose Sign In with GitHub and approve this Mac in
                            your browser. It joins your{' '}
                            <Link
                                href={DeviceController.index()}
                                className="underline decoration-rail underline-offset-4 hover:decoration-current"
                            >
                                devices
                            </Link>
                            .
                        </InstallStep>
                        <InstallStep index={3} command="git push" isLast>
                            Push to your first remote.
                        </InstallStep>
                    </ol>
                </section>

                {release !== null && !release.notarized && <FirstOpen />}

                <section
                    id="faq"
                    aria-labelledby="faq-title"
                    className="scroll-mt-14 border-t px-4 py-6 sm:px-6"
                >
                    <h2
                        id="faq-title"
                        className="text-lg font-semibold tracking-tight"
                    >
                        Questions
                    </h2>
                    <div className="mt-3 divide-y border-y">
                        <Question title="What does it need?">
                            macOS{' '}
                            {release?.minimum_system_version.replace(
                                /\.0$/,
                                '',
                            ) ?? '14'}{' '}
                            Sonoma or later, on Apple silicon or Intel, and a{' '}
                            <WordmarkText>/dev/null</WordmarkText> account with
                            Remotes turned on. It's free.
                        </Question>
                        {release !== null && !release.notarized && (
                            <Question title="Why does macOS ask me to approve it?">
                                macOS opens apps from outside the App Store
                                without asking only once Apple has notarized
                                them. This build isn't notarized yet, so macOS
                                can't vouch for it and asks you first. You
                                approve it once; updates you install the same
                                way ask again. You can check your download
                                against the SHA-256 checksum above with{' '}
                                <code className="font-mono text-[13px]">
                                    shasum -a 256 DevNull.dmg
                                </code>
                                .
                            </Question>
                        )}
                        <Question title="How does signing in work?">
                            Like the phone app, with a key instead of a
                            password. The app makes a key in your Keychain,
                            opens <WordmarkText>/dev/null</WordmarkText> in your
                            browser to approve it, and signs every request with
                            it. No password or token is stored, and the Mac
                            shows up in{' '}
                            <Link href={DeviceController.index()}>
                                Settings → Devices
                            </Link>
                            , where you can revoke it.
                        </Question>
                        <Question title="How do I update it?">
                            Choose Check for Updates… in the app menu. It
                            compares your build with the one on this page and
                            brings you here when there's a newer one. Download
                            it, quit the app, and drag the new one onto
                            Applications to replace it. You stay signed in.
                        </Question>
                        <Question title="What does it keep on my Mac?">
                            Its key, in your Keychain, and a few preferences.
                            Messages and images stay in memory and are gone when
                            you quit; banners never show what a message says.
                            The update check sends nothing about you. See the{' '}
                            <Link href={PrivacyController.show()}>
                                privacy policy
                            </Link>
                            .
                        </Question>
                        <Question title="How do I uninstall it?">
                            Choose Sign Out in its settings, which deletes the
                            key from your Keychain, then quit it and move it
                            from Applications to the Trash. Revoke the Mac in{' '}
                            <Link href={DeviceController.index()}>
                                Settings → Devices
                            </Link>{' '}
                            too.
                        </Question>
                    </div>
                </section>
            </article>
        </>
    );
}

Index.layout = AppLayout;

function DownloadPanel({
    release,
    isMac,
}: {
    release: Release | null;
    isMac: boolean;
}) {
    const [, copy] = useClipboard();
    const [hasCopied, setHasCopied] = useState(false);

    useEffect(() => {
        if (!hasCopied) {
            return;
        }

        const timeout = window.setTimeout(() => setHasCopied(false), 2000);

        return () => window.clearTimeout(timeout);
    }, [hasCopied]);

    if (release === null) {
        return (
            <div className="mt-6 space-y-2">
                <Button size="lg" disabled className="h-11">
                    <Download aria-hidden />
                    Download for Mac
                </Button>
                <p className="font-mono text-[13px] text-muted-foreground">
                    fatal: no release published yet
                </p>
            </div>
        );
    }

    const copySha = async () => {
        if (await copy(release.sha256)) {
            setHasCopied(true);
            toast(
                <span className="font-mono text-[13px]">
                    Copied the SHA-256 checksum
                </span>,
            );
        } else {
            toast.error(
                <span className="font-mono text-[13px]">
                    fatal: could not copy the checksum
                </span>,
            );
        }
    };

    return (
        <div className="mt-6">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                <Button
                    size="lg"
                    variant={isMac ? 'default' : 'outline'}
                    className="h-11"
                    asChild
                >
                    <a href={release.download_url} download>
                        <Download aria-hidden />
                        {isMac ? 'Download for Mac' : 'Download anyway'}
                    </a>
                </Button>
                <p className="font-mono text-[13px] text-muted-foreground tabular-nums">
                    {megabytes(release.size)} · Universal · macOS{' '}
                    {release.minimum_system_version.replace(/\.0$/, '')}+
                </p>
            </div>

            {/* Both notes are one line on wide screens and two on phones, so swapping them never moves the page. */}
            <p className="mt-3 min-h-[3lh] text-sm text-pretty text-muted-foreground sm:min-h-[2lh]">
                {isMac
                    ? `Released ${releaseDate(release.released_at)}. ${
                          release.notarized
                              ? 'Signed with a Developer ID and notarized by Apple.'
                              : 'Not notarized by Apple yet, so macOS asks you to approve it once.'
                      }`
                    : 'This looks like it isn’t a Mac. The app runs only on macOS, so download it here and copy it to your Mac, or open this page there.'}
            </p>

            <div className="mt-3 flex min-w-0 items-center gap-2 rounded-lg border bg-background py-1.5 pr-1.5 pl-3">
                <span className="shrink-0 font-mono text-xs text-muted-foreground">
                    sha256
                </span>
                <code
                    title={release.sha256}
                    className="min-w-0 flex-1 truncate font-mono text-xs"
                >
                    {release.sha256}
                </code>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7 shrink-0"
                    onClick={copySha}
                    aria-label="Copy the SHA-256 checksum"
                >
                    {hasCopied ? (
                        <Check aria-hidden className="size-3.5" />
                    ) : (
                        <Copy aria-hidden className="size-3.5" />
                    )}
                </Button>
            </div>
        </div>
    );
}

function InstallStep({
    index,
    command,
    isLast = false,
    children,
}: {
    index: number;
    command: string;
    isLast?: boolean;
    children: ReactNode;
}) {
    return (
        <li className="flex">
            <div aria-hidden className="relative w-10 shrink-0 sm:w-14">
                {!isLast && (
                    <span
                        className="absolute top-3 bottom-0 left-1/2 w-0.5 origin-top -translate-x-1/2 bg-(--lane-0) motion-safe:animate-rail-draw"
                        style={{ animationDelay: `${index * 140}ms` }}
                    />
                )}
                <span
                    className={cn(
                        'absolute top-3 left-1/2 size-3.5 -translate-1/2 rounded-full border-[3px] border-background',
                        isLast
                            ? 'bg-background shadow-[0_0_0_2px_var(--rail)]'
                            : 'bg-(--lane-0) shadow-[0_0_0_2px_var(--lane-0)]',
                    )}
                />
            </div>
            <div className={cn('min-w-0 flex-1', { 'pb-6': !isLast })}>
                <p className="font-mono text-[13px] leading-6 text-muted-foreground">
                    $ {command}
                </p>
                <p className="text-pretty">{children}</p>
            </div>
        </li>
    );
}

function FirstOpen() {
    return (
        <section
            id="first-open"
            aria-labelledby="first-open-title"
            className="scroll-mt-14 border-t px-4 py-6 sm:px-6"
        >
            <h2
                id="first-open-title"
                className="text-lg font-semibold tracking-tight"
            >
                If macOS blocks it
            </h2>
            <p className="mt-2 text-pretty text-muted-foreground">
                This build isn't notarized by Apple yet, so the first time you
                open it, macOS stops it:
            </p>
            <blockquote className="mt-3 rounded-lg border bg-background px-4 py-3 text-sm">
                <p className="font-semibold">“DevNull” Not Opened</p>
                <p className="mt-1 text-pretty text-muted-foreground">
                    Apple could not verify “DevNull” is free of malware that may
                    harm your Mac or compromise your privacy.
                </p>
            </blockquote>
            <p className="mt-3 text-pretty text-muted-foreground">
                Approve it once:
            </p>
            <ol className="mt-4 list-decimal space-y-2 pl-5 marker:font-mono marker:text-[13px] marker:text-muted-foreground">
                <li>Click Done, not Move to Trash.</li>
                <li>
                    Open System Settings → Privacy &amp; Security, and scroll
                    down to Security.
                </li>
                <li>
                    Next to “DevNull” was blocked to protect your Mac, click
                    Open Anyway.
                </li>
                <li>
                    Click Open Anyway again, and enter your password or use
                    Touch ID.
                </li>
            </ol>
            <p className="mt-3 text-sm text-pretty text-muted-foreground">
                On macOS 14 Sonoma, Control-click{' '}
                <WordmarkText>/dev/null</WordmarkText> in Applications, choose
                Open, then click Open.
            </p>
        </section>
    );
}

function Question({ title, children }: { title: string; children: ReactNode }) {
    return (
        <details className="group">
            <summary className="flex cursor-pointer list-none items-center justify-between gap-4 py-3 font-medium [&::-webkit-details-marker]:hidden">
                {title}
                <span
                    aria-hidden
                    className="font-mono text-[13px] text-muted-foreground group-open:hidden"
                >
                    +
                </span>
                <span
                    aria-hidden
                    className="hidden font-mono text-[13px] text-muted-foreground group-open:inline"
                >
                    −
                </span>
            </summary>
            <p className="pb-4 text-pretty text-muted-foreground [&_a]:underline [&_a]:decoration-rail [&_a]:underline-offset-4 [&_a:hover]:decoration-current">
                {children}
            </p>
        </details>
    );
}
