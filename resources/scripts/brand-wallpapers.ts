/**
 * Renders the brand wallpapers into public/images/brand.
 *
 * Each wallpaper is laid out as HTML with the self-hosted Geist fonts and
 * screenshotted by Chromium at its final pixel size, so the PNGs match the
 * app's rendering exactly.
 *
 *   node --experimental-strip-types resources/scripts/brand-wallpapers.ts
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { chromium } from 'playwright';

const root = resolve(import.meta.dirname, '../..');
const out = resolve(root, 'public/images/brand');
const scratch = resolve(root, 'storage/app/wallpapers');

type Mode = 'dark' | 'light';
type Format = 'desktop' | 'phone';

type Palette = {
    shell: string;
    ink: string;
    muted: string;
    hairline: string;
    rail: string;
    accent: string;
    lanes: string[];
};

const palettes: Record<Mode, Palette> = {
    dark: {
        shell: '#111112',
        ink: '#ececec',
        muted: '#a1a1a6',
        hairline: '#2a2a2d',
        rail: '#36363a',
        accent: '#ff8a4c',
        lanes: [
            '#ff8a4c',
            '#51cf66',
            '#da77f2',
            '#3bc9db',
            '#f06595',
            '#fcc419',
        ],
    },
    light: {
        shell: '#f6f7f8',
        ink: '#121417',
        muted: '#5b616b',
        hairline: '#e1e4e8',
        rail: '#d3d7dd',
        accent: '#d9480f',
        lanes: [
            '#d9480f',
            '#2f9e44',
            '#9c36b5',
            '#0c8599',
            '#c2255c',
            '#b7791f',
        ],
    },
};

const formats: Record<Format, { width: number; height: number }> = {
    desktop: { width: 3840, height: 2160 },
    phone: { width: 1290, height: 2796 },
};

type Canvas = {
    mode: Mode;
    format: Format;
    width: number;
    height: number;
    palette: Palette;
    /** Scale relative to the 4K desktop, so strokes and type keep their weight on the phone. */
    scale: number;
};

const font = (file: string) =>
    pathToFileURL(resolve(root, 'resources/fonts', file)).href;

const brandAsset = (name: string) =>
    readFileSync(
        resolve(root, 'public/images/brand', `dev-null-${name}.svg`),
        'utf8',
    );

/**
 * The mark's two slashes, from the mark SVG: the solid one in ink, the outlined one in the accent.
 */
const markSvg = (palette: Palette, attributes: string) =>
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="1.6 1.5 45 45" ${attributes}>` +
    `<path fill="${palette.ink}" d="M2 42h10L26 6H16Z"/>` +
    `<path fill="${palette.accent}" fill-rule="evenodd" clip-rule="evenodd" d="M17.81 43.5h13.22L46.19 4.5H32.97ZM22.19 40.5l12.84-33h6.78l-12.84 33Z"/>` +
    `</svg>`;

const page = (canvas: Canvas, body: string, css = '') => `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@font-face { font-family: Geist; font-weight: 400; src: url(${font('Geist-Regular.ttf')}); }
@font-face { font-family: Geist; font-weight: 600; src: url(${font('Geist-SemiBold.ttf')}); }
@font-face { font-family: 'Geist Mono'; font-weight: 400; src: url(${font('GeistMono-Regular.ttf')}); }
@font-face { font-family: 'Geist Mono'; font-weight: 500; src: url(${font('GeistMono-Medium.ttf')}); }
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: ${canvas.width}px; height: ${canvas.height}px; overflow: hidden; }
body { background: ${canvas.palette.shell}; color: ${canvas.palette.ink}; font-family: Geist, sans-serif; position: relative; -webkit-font-smoothing: antialiased; }
.mono { font-family: 'Geist Mono', monospace; font-feature-settings: 'tnum'; }
${css}
</style>
</head>
<body>${body}</body>
</html>`;

/**
 * A seven character hash for a commit message, stable across runs.
 */
function hash(message: string) {
    let h = 2166136261;

    for (const char of message) {
        h ^= char.charCodeAt(0);
        h = Math.imul(h, 16777619) >>> 0;
    }

    return h.toString(16).padStart(8, '0').slice(0, 7);
}

/**
 * Mark: the two slashes alone, large and centered.
 */
function mark(canvas: Canvas) {
    const { palette, width, height, format } = canvas;
    const size = format === 'desktop' ? height * 0.58 : width * 0.64;
    const glow =
        canvas.mode === 'dark'
            ? `<div style="position:absolute;left:50%;top:50%;width:${size * 2.2}px;height:${size * 2.2}px;transform:translate(-50%,-50%);border-radius:50%;background:radial-gradient(circle, ${palette.accent}14 0%, ${palette.shell}00 62%)"></div>`
            : `<div style="position:absolute;left:50%;top:50%;width:${size * 2.2}px;height:${size * 2.2}px;transform:translate(-50%,-50%);border-radius:50%;background:radial-gradient(circle, #ffffff 0%, ${palette.shell}00 62%)"></div>`;

    return page(
        canvas,
        `${glow}<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:${size}px;height:${size}px">${markSvg(palette, 'width="100%" height="100%"')}</div>`,
    );
}

/**
 * Redirect: the one line every developer has typed, set as a wordmark.
 */
function redirect(canvas: Canvas) {
    const { palette, format, width } = canvas;
    const wordmark = brandAsset(`wordmark-${canvas.mode}`).replace(
        '<svg ',
        '<svg class="wordmark" ',
    );

    const css = `
        .line { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); white-space: nowrap; font-weight: 600; letter-spacing: -0.04em; line-height: 1; color: ${palette.muted}; display: flex; align-items: baseline; }
        .line .gap { width: 0.26em; }
        .wordmark { height: 0.93em; position: relative; top: 0.145em; display: block; }
        .stack { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); font-weight: 600; letter-spacing: -0.04em; line-height: 1.02; color: ${palette.muted}; }
        .stack .wordmark { position: static; display: block; height: 0.93em; margin: 0.05em 0; }
    `;

    if (format === 'desktop') {
        return page(
            canvas,
            `<div class="line" data-fit="${width * 0.84}"><span>&gt;</span><span class="gap"></span>${wordmark}<span class="gap"></span><span>2&gt;&amp;1</span></div>`,
            css,
        );
    }

    return page(
        canvas,
        `<div class="stack" data-fit="${width * 0.82}"><div>&gt;</div>${wordmark}<div>2&gt;&amp;1</div></div>`,
        css,
    );
}

/**
 * Log: the project's own history, as git would print it, under the logo.
 */
function log(canvas: Canvas) {
    const { palette, format, scale, width, height } = canvas;

    const messages = [
        'feat: brand',
        'feat: reply to a commit from the terminal with --reply',
        'feat: add image attachments to commits',
        'fix: call the app /dev/null and point to dev-null.sh',
        'feat: swipe between log lanes on touch screens',
        'feat: floating commit button on mobile',
        'docs: security policy',
        'chore: removes octane',
        'fix: keep the trending log ranked after committing from it',
        'feat: react to remote messages with emoji',
        'feat: analytics',
        'chore: uses pg 18 as in production',
        'chore: uses node 26 as in production',
        'feat: short numbers where needed',
        'feat: open chat images in the image viewer',
        'fix: notification counter should take filters into account',
        'chore: bump deps',
        'feat: links on about',
        'feat: improve automod to take recent reports into account',
        'chore: removes down methods',
        'chore: adds test on migrations going up only',
        'feat: read a thread an author writes as replies to themselves',
        'ci: pin workflows',
        'docs: security guidelines',
        'fix: usage of multiple with awk',
        'feat: login locally, about, animation if commit being reviewed',
        'chore: engine 1.4.3',
        'feat: only offer new commits on the following and latest logs',
        'fix: keep browser swipe navigation from overriding lane swipes',
        'chore: style',
        'feat: commit from the terminal',
        'fix: a node pops onto the rail when you commit',
        'feat: rm {hash} → /dev/null',
        'chore: updates about',
        'feat: everyone picks a lane',
        'fix: dividers never cross the rail',
        'feat: a star under the commit body',
        'docs: document app features in the knowledge base',
        'feat: mentions',
        'chore: the register is a well-lit terminal',
    ];

    const fontSize = (format === 'desktop' ? 44 : 34) * scale;
    const lineHeight = fontSize * 1.75;
    const inset = (format === 'desktop' ? 160 : 72) * scale;
    const lines = Math.ceil((height - inset) / lineHeight) + 1;
    const maxChars = Math.floor((width - inset * 2) / (fontSize * 0.6)) - 12;

    const rows: string[] = [];

    rows.push(
        `<div class="row"><span class="prompt">$</span> git log --oneline</div>`,
    );

    for (let index = 0; index < lines; index++) {
        const message = messages[index % messages.length];
        const text =
            message.length > maxChars
                ? message.slice(0, maxChars - 1) + '…'
                : message;
        const lane = palette.lanes[parseInt(hash(message), 16) % 6];
        const head =
            index === 0 ? `<span class="ref">(HEAD -&gt; main)</span> ` : '';

        rows.push(
            `<div class="row"><span class="star" style="color:${lane}">*</span> <span class="hash" style="color:${lane}">${hash(message)}</span> ${head}${text}</div>`,
        );
    }

    const opacity = canvas.mode === 'dark' ? 0.38 : 0.5;
    const logoHeight = (format === 'desktop' ? 260 : 150) * scale;
    const halo = logoHeight * 1.4;
    const logo = brandAsset(`logo-${canvas.mode}`).replace(
        '<svg ',
        `<svg height="${logoHeight}" style="display:block" `,
    );

    const css = `
        .log { position: absolute; left: ${inset}px; top: ${inset}px; font-size: ${fontSize}px; line-height: ${lineHeight}px; color: ${palette.muted}; opacity: ${opacity}; white-space: nowrap; }
        .row { }
        .prompt { color: ${palette.ink}; }
        .hash { font-weight: 500; }
        .ref { color: ${palette.ink}; }
        .logo { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); padding: ${halo * 0.5}px ${halo}px; background: ${palette.shell}; box-shadow: 0 0 ${halo}px ${halo * 0.6}px ${palette.shell}; }
    `;

    return page(
        canvas,
        `<div class="log mono">${rows.join('')}</div><div class="logo">${logo}</div>`,
        css,
    );
}

const designs = { mark, redirect, log } as const;

/**
 * Type that has to span a given width is measured and scaled in the browser.
 */
function fit() {
    for (const element of document.querySelectorAll<HTMLElement>(
        '[data-fit]',
    )) {
        const target = Number(element.dataset.fit);
        element.style.fontSize = '100px';
        const measured = element.getBoundingClientRect().width;
        element.style.fontSize = `${(100 * target) / measured}px`;
    }
}

async function main() {
    const only = process.argv.slice(2);

    mkdirSync(out, { recursive: true });
    mkdirSync(scratch, { recursive: true });

    const browser = await chromium.launch();

    for (const [design, render] of Object.entries(designs)) {
        if (only.length > 0 && !only.includes(design)) continue;

        for (const mode of ['dark', 'light'] as const) {
            for (const format of ['desktop', 'phone'] as const) {
                const { width, height } = formats[format];
                const canvas: Canvas = {
                    mode,
                    format,
                    width,
                    height,
                    palette: palettes[mode],
                    scale: format === 'desktop' ? 1 : 0.9,
                };

                const name = `dev-null-wallpaper-${design}-${mode}-${format}`;
                const html = resolve(scratch, `${name}.html`);
                writeFileSync(html, render(canvas));

                const tab = await browser.newPage({
                    viewport: { width, height },
                    deviceScaleFactor: 1,
                });
                await tab.goto(pathToFileURL(html).href);
                await tab.evaluate(() => document.fonts.ready);

                // Type that has to span a given width is measured and scaled in the browser.
                await tab.evaluate(() => {
                    for (const element of document.querySelectorAll<HTMLElement>(
                        '[data-fit]',
                    )) {
                        const target = Number(element.dataset.fit);
                        element.style.fontSize = '100px';
                        const measured = element.getBoundingClientRect().width;
                        element.style.fontSize = `${(100 * target) / measured}px`;
                    }
                });

                await tab.screenshot({ path: resolve(out, `${name}.png`) });
                await tab.close();

                // A half size copy of the desktop wallpaper, for the brand page.
                if (format === 'desktop') {
                    const preview = await browser.newPage({
                        viewport: { width, height },
                        deviceScaleFactor: 0.5,
                    });
                    await preview.goto(pathToFileURL(html).href);
                    await preview.evaluate(() => document.fonts.ready);
                    await preview.evaluate(fit);
                    await preview.screenshot({
                        path: resolve(out, `${name}-preview.png`),
                    });
                    await preview.close();
                }

                console.log(name);
            }
        }
    }

    await browser.close();
}

await main();
