import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Fragment } from 'react';
import { toast } from 'sonner';
import {
    DocumentList,
    DocumentPage,
    DocumentSection,
} from '@/components/document-page';
import { WordmarkText } from '@/components/wordmark-text';
import { useClipboard } from '@/hooks/use-clipboard';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';

type Theme = 'light' | 'dark' | 'black' | 'white';

const themeLabels: Record<Theme, string> = {
    light: 'On light',
    dark: 'On dark',
    black: 'Black',
    white: 'White',
};

/**
 * The background each logo variant is previewed on, the one it was drawn for.
 */
const themeBackgrounds: Record<Theme, string> = {
    light: 'bg-[#f6f7f8]',
    dark: 'bg-[#111112]',
    black: 'bg-white',
    white: 'bg-[#d9480f]',
};

const logos = [
    {
        kind: 'logo',
        title: 'Logo',
        description:
            'The mark next to the wordmark. Use it wherever there is room.',
    },
    {
        kind: 'mark',
        title: 'Mark',
        description:
            'The two slashes on their own. Use it for avatars, favicons, and small spaces.',
    },
    {
        kind: 'wordmark',
        title: 'Wordmark',
        description:
            'The name on its own, for when the mark is already nearby.',
    },
] as const;

const backgrounds = [
    {
        kind: 'wallpaper',
        title: 'Wallpaper',
        size: '3840 × 2160',
        aspect: 'aspect-video',
    },
    {
        kind: 'banner',
        title: 'Profile banner',
        size: '1500 × 500',
        aspect: 'aspect-3/1',
    },
    {
        kind: 'social',
        title: 'Social card',
        size: '1200 × 630',
        aspect: 'aspect-1200/630',
    },
] as const;

/**
 * The wallpapers rendered by resources/scripts/brand-wallpapers.ts, each in light and dark, for desktops and phones.
 */
const wallpapers = [
    {
        kind: 'mark',
        title: 'Mark',
        description: 'The two slashes, alone.',
    },
    {
        kind: 'redirect',
        title: 'Redirect',
        description: 'The one line every developer has typed.',
    },
    {
        kind: 'log',
        title: 'Log',
        description: 'Our own history, as git prints it.',
    },
] as const;

const wallpaperFormats = [
    { kind: 'desktop', title: 'Desktop', size: '3840 × 2160' },
    { kind: 'phone', title: 'Phone', size: '1290 × 2796' },
] as const;

const colors = [
    { name: 'Ink', light: '#121417', dark: '#ececec' },
    { name: 'Shell', light: '#f6f7f8', dark: '#111112' },
    { name: 'Accent', light: '#d9480f', dark: '#ff8a4c' },
    { name: 'Primary', light: '#3b6fe0', dark: '#7aa2f7' },
];

const lanes = [
    { light: '#d9480f', dark: '#ff8a4c' },
    { light: '#2f9e44', dark: '#51cf66' },
    { light: '#9c36b5', dark: '#da77f2' },
    { light: '#0c8599', dark: '#3bc9db' },
    { light: '#c2255c', dark: '#f06595' },
    { light: '#b7791f', dark: '#fcc419' },
];

function asset(name: string, extension: 'svg' | 'png' | 'zip') {
    return `/images/brand/dev-null-${name}.${extension}`;
}

function DownloadLink({
    href,
    title,
    children,
}: {
    href: string;
    title?: string;
    children: string;
}) {
    return (
        <a
            href={href}
            download
            title={title}
            className="inline-flex h-7 items-center gap-1 rounded-md border bg-background px-2 font-mono text-xs text-foreground no-underline! transition-colors hover:bg-muted"
        >
            <Download aria-hidden className="size-3.5" />
            {children}
        </a>
    );
}

function LogoVariant({ kind, theme }: { kind: string; theme: Theme }) {
    const name = `${kind}-${theme}`;

    return (
        <figure className="overflow-hidden rounded-lg border">
            <div
                className={cn(
                    'flex h-32 items-center justify-center p-6',
                    themeBackgrounds[theme],
                )}
            >
                <img
                    src={asset(name, 'svg')}
                    alt=""
                    className={cn(
                        'max-h-full max-w-full',
                        kind === 'mark' ? 'h-14' : 'h-10',
                    )}
                />
            </div>
            <figcaption className="flex items-center justify-between gap-2 border-t bg-card px-3 py-2">
                <span className="text-sm text-muted-foreground">
                    {themeLabels[theme]}
                </span>
                <span className="flex gap-1.5">
                    <DownloadLink href={asset(name, 'svg')}>SVG</DownloadLink>
                    <DownloadLink href={asset(name, 'png')}>PNG</DownloadLink>
                </span>
            </figcaption>
        </figure>
    );
}

function Background({
    background,
    mode,
}: {
    background: (typeof backgrounds)[number];
    mode: 'light' | 'dark';
}) {
    const name = `${background.kind}-${mode}`;

    return (
        <figure className="overflow-hidden rounded-lg border">
            <img
                src={asset(name, 'png')}
                alt={`${background.title}, ${mode}`}
                loading="lazy"
                className={cn(
                    'w-full bg-muted object-cover',
                    background.aspect,
                )}
            />
            <figcaption className="flex items-center justify-between gap-2 border-t bg-card px-3 py-2">
                <span className="text-sm">
                    {background.title}{' '}
                    <span className="font-mono text-xs text-muted-foreground">
                        {mode} · {background.size}
                    </span>
                </span>
                <span className="flex gap-1.5">
                    <DownloadLink href={asset(name, 'svg')}>SVG</DownloadLink>
                    <DownloadLink href={asset(name, 'png')}>PNG</DownloadLink>
                </span>
            </figcaption>
        </figure>
    );
}

function Wallpaper({
    kind,
    title,
    mode,
}: {
    kind: string;
    title: string;
    mode: 'light' | 'dark';
}) {
    return (
        <figure className="overflow-hidden rounded-lg border">
            <img
                src={asset(`wallpaper-${kind}-${mode}-desktop-preview`, 'png')}
                alt={`${title} wallpaper, ${mode}`}
                loading="lazy"
                width={1920}
                height={1080}
                className="aspect-video w-full bg-muted object-cover"
            />
            <figcaption className="flex flex-wrap items-center justify-between gap-2 border-t bg-card px-3 py-2">
                <span className="text-sm text-muted-foreground">
                    {themeLabels[mode]}
                </span>
                <span className="flex gap-1.5">
                    {wallpaperFormats.map((format) => (
                        <DownloadLink
                            key={format.kind}
                            href={asset(
                                `wallpaper-${kind}-${mode}-${format.kind}`,
                                'png',
                            )}
                            title={format.size}
                        >
                            {format.title}
                        </DownloadLink>
                    ))}
                </span>
            </figcaption>
        </figure>
    );
}

function Swatch({ name, hex }: { name: string; hex: string }) {
    const [, copy] = useClipboard();

    const copyHex = async () => {
        if (await copy(hex)) {
            toast(<span className="font-mono text-[13px]">Copied {hex}</span>);
        }
    };

    return (
        <button
            type="button"
            onClick={copyHex}
            className="group flex items-center gap-2.5 rounded-lg border bg-card p-2 text-left transition-colors hover:bg-muted"
            aria-label={`Copy ${name} ${hex}`}
        >
            <span
                aria-hidden
                className="size-8 shrink-0 rounded-md border"
                style={{ backgroundColor: hex }}
            />
            <span className="min-w-0 leading-tight">
                <span className="block text-sm font-medium">{name}</span>
                <span className="block font-mono text-xs text-muted-foreground">
                    {hex}
                </span>
            </span>
        </button>
    );
}

export default function Show() {
    return (
        <>
            <Head title="Brand" />

            <DocumentPage
                title="Brand"
                meta={
                    <a
                        href={asset('brand', 'zip')}
                        download
                        className="inline-flex items-center gap-1 transition-colors hover:text-foreground"
                    >
                        <Download aria-hidden className="size-3.5" />
                        download all
                    </a>
                }
            >
                <section className="space-y-3 px-4 py-6 text-[15px] text-pretty sm:px-6">
                    <p>
                        Writing about <WordmarkText>/dev/null</WordmarkText>,
                        linking to it, or putting it on a slide? Everything you
                        need is here: the logo, the colors, and a few
                        backgrounds. Every logo comes as an SVG with the text
                        outlined, so it looks the same without Geist installed,
                        and as a PNG.
                    </p>
                    <p>
                        <a
                            href={asset('brand', 'zip')}
                            download
                            className="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            <Download aria-hidden className="size-4" />
                            Download everything
                        </a>
                    </p>
                </section>

                {logos.map((logo) => (
                    <DocumentSection
                        key={logo.kind}
                        id={logo.kind}
                        title={logo.title}
                    >
                        <p>{logo.description}</p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {(['light', 'dark', 'black', 'white'] as const).map(
                                (theme) => (
                                    <LogoVariant
                                        key={theme}
                                        kind={logo.kind}
                                        theme={theme}
                                    />
                                ),
                            )}
                        </div>
                    </DocumentSection>
                ))}

                <DocumentSection id="colors" title="Colors">
                    <p>
                        Ink on shell, with the accent orange for the outlined
                        slash. Click a swatch to copy it.
                    </p>
                    {(['light', 'dark'] as const).map((mode) => (
                        <div key={mode} className="space-y-2">
                            <h3 className="font-mono text-[13px] text-muted-foreground">
                                {mode}
                            </h3>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                {colors.map((color) => (
                                    <Swatch
                                        key={color.name}
                                        name={color.name}
                                        hex={color[mode]}
                                    />
                                ))}
                            </div>
                        </div>
                    ))}
                    <p>
                        Every developer picks a lane, and lanes color the graph.
                        Use them for illustrations, never for the logo.
                    </p>
                    {(['light', 'dark'] as const).map((mode) => (
                        <div
                            key={mode}
                            className="grid grid-cols-2 gap-2 sm:grid-cols-3"
                        >
                            {lanes.map((lane, index) => (
                                <Swatch
                                    key={index}
                                    name={`Lane ${index}${mode === 'dark' ? ' (dark)' : ''}`}
                                    hex={lane[mode]}
                                />
                            ))}
                        </div>
                    ))}
                </DocumentSection>

                <DocumentSection id="typography" title="Typography">
                    <p>
                        <a
                            href="https://vercel.com/font"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Geist
                        </a>{' '}
                        for everything, bold and tightly tracked for the
                        wordmark, and Geist Mono for hashes, timestamps, and
                        anything that looks like a terminal.
                    </p>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-lg border bg-card p-4">
                            <p className="text-3xl font-semibold tracking-tight">
                                Aa
                            </p>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Geist, 400 to 700
                            </p>
                        </div>
                        <div className="rounded-lg border bg-card p-4">
                            <p className="font-mono text-3xl">Aa</p>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Geist Mono, 400 and 500
                            </p>
                        </div>
                    </div>
                </DocumentSection>

                <DocumentSection id="backgrounds" title="Backgrounds">
                    <p>
                        The logo on a commit graph, for your desktop, your
                        phone, your profile header, or a link preview.
                    </p>
                    <div className="space-y-4">
                        {backgrounds.map((background) => (
                            <Fragment key={background.kind}>
                                {(['light', 'dark'] as const).map((mode) => (
                                    <Background
                                        key={mode}
                                        background={background}
                                        mode={mode}
                                    />
                                ))}
                                {background.kind === 'wallpaper' &&
                                    wallpapers.map((design, index) => (
                                        <div
                                            key={design.kind}
                                            id={
                                                index === 0
                                                    ? 'wallpapers'
                                                    : undefined
                                            }
                                            className="scroll-mt-18 space-y-3"
                                        >
                                            <h3 className="font-medium">
                                                {design.title}{' '}
                                                <span className="font-normal text-muted-foreground">
                                                    {design.description}
                                                </span>
                                            </h3>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                {(
                                                    ['dark', 'light'] as const
                                                ).map((mode) => (
                                                    <Wallpaper
                                                        key={mode}
                                                        kind={design.kind}
                                                        title={design.title}
                                                        mode={mode}
                                                    />
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                            </Fragment>
                        ))}
                    </div>
                </DocumentSection>

                <DocumentSection id="usage" title="Usage">
                    <p>Do:</p>
                    <DocumentList
                        items={[
                            <>
                                Write the name as <code>/dev/null</code>:
                                lowercase, with both slashes, and no space.
                            </>,
                            'Use the light logo on light backgrounds and the dark logo on dark ones.',
                            'Use the black or white logo on photos or brand colors that are not ours.',
                            'Leave clear space around the logo, at least the width of the mark.',
                            'Keep the mark at least 16px tall, and the logo at least 20px.',
                        ]}
                    />
                    <p>Don't:</p>
                    <DocumentList
                        items={[
                            'Recolor, stretch, rotate, or add effects such as shadows or outlines.',
                            'Fill the outlined slash, or outline the solid ones.',
                            'Retype the wordmark in another font; use the files.',
                            'Use the logo in a way that suggests we sponsor or endorse you.',
                        ]}
                    />
                    <p className="text-muted-foreground">
                        Tip: right-click the logo in the sidebar for a shortcut
                        back here.
                    </p>
                </DocumentSection>
            </DocumentPage>
        </>
    );
}

Show.layout = AppLayout;
