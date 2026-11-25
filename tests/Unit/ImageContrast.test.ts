import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import {
    mostOpaque,
    visibility,
} from '../../resources/js/lib/image-contrast.ts';
import type { Visibility } from '../../resources/js/lib/image-contrast.ts';

const light = '#eef0f2';
const dark = '#232326';

// A 10×10 icon: `share` of its pixels are `color`, the rest `rest`, as RGBA.
function icon(
    color: [number, number, number, number],
    share: number,
    rest: [number, number, number, number] = [0, 0, 0, 0],
) {
    const pixels = new Uint8ClampedArray(400);
    for (let i = 0; i < 100; i++) {
        pixels.set(i < share * 100 ? color : rest, i * 4);
    }
    return pixels;
}

type Rgba = [number, number, number, number];

// A 10×10 icon drawn as rows of letters, each a color; "." is transparent.
function draw(rows: string[], colors: Record<string, Rgba>) {
    const pixels = new Uint8ClampedArray(400);
    rows.join('')
        .split('')
        .forEach((letter, i) => {
            pixels.set(colors[letter] ?? [0, 0, 0, 0], i * 4);
        });
    return pixels;
}

const colors: Record<string, Rgba> = {
    K: [0, 0, 0, 255],
    W: [255, 255, 255, 255],
    G: [235, 235, 235, 255],
    O: [255, 138, 76, 255],
    // Light gray, under half opaque.
    g: [235, 235, 235, 100],
};

await test('judges icons against the light and dark backgrounds', () => {
    // Name, pixels, and how each shows in light and in dark mode.
    const cases: [string, Uint8ClampedArray, Visibility, Visibility][] = [
        ['dark logo', icon([0, 0, 0, 255], 0.3), 'easy', 'hard'],
        ['white logo', icon([255, 255, 255, 255], 0.3), 'hard', 'easy'],
        ['mid-gray logo', icon([119, 119, 119, 255], 0.3), 'easy', 'easy'],
        // 1.8:1 on the light background: there, but short of WCAG's 3:1.
        ['light-gray logo', icon([180, 180, 180, 255], 0.3), 'wcag', 'easy'],
        ['thin dark-gray logo', icon([80, 80, 80, 255], 0.04), 'easy', 'wcag'],
        // Deliberately translucent: judged as it is laid over the background.
        [
            '30% opaque black watermark',
            icon([0, 0, 0, 77], 0.3),
            'wcag',
            'hard',
        ],
        ['white square', icon([255, 255, 255, 255], 1), 'hard', 'easy'],
        [
            'nearly transparent dark logo',
            icon([0, 0, 0, 40], 0.3),
            'hard',
            'hard',
        ],
        ['transparent image', icon([0, 0, 0, 0], 1), 'hard', 'hard'],
        ['faint image', icon([0, 0, 0, 20], 1), 'hard', 'hard'],
        [
            // The white square backs the logo, and the white in the O is its hole.
            'logo on its own rounded white square',
            draw(
                [
                    '.WWWWWWWW.',
                    'WWWWWWWWWW',
                    'WWKKKKKKWW',
                    'WWKWWWWKWW',
                    'WWKWWWWKWW',
                    'WWKWWWWKWW',
                    'WWKWWWWKWW',
                    'WWKKKKKKWW',
                    'WWWWWWWWWW',
                    '.WWWWWWWW.',
                ],
                colors,
            ),
            'easy',
            'easy',
        ],
        [
            'opaque image of two halves',
            draw(Array(10).fill('KKKKKGGGGG'), colors),
            'easy',
            'easy',
        ],
        [
            // Orange is 2:1 on the light background: there, but short of WCAG's 3:1.
            'orange and light-gray parts',
            draw(
                ['OOO.......', ...Array(8).fill('..........'), '.......GGG'],
                colors,
            ),
            'wcag',
            'easy',
        ],
        [
            'black and light-gray parts, apart',
            draw(
                ['KKK.......', ...Array(8).fill('..........'), '.......GGG'],
                colors,
            ),
            'partly',
            'partly',
        ],
        [
            'slanted black and light-gray halves, touching',
            draw(
                [
                    '.....KKGG.',
                    '.....KKGG.',
                    '....KKGG..',
                    '....KKGG..',
                    '...KKGG...',
                    '...KKGG...',
                    '..KKGG....',
                    '..KKGG....',
                    '.KKGG.....',
                    '.KKGG.....',
                ],
                colors,
            ),
            'partly',
            'partly',
        ],
        [
            // The logo cuts its backing in two; the halves still back it together.
            'logo across its own white backing',
            draw(
                [
                    'WW........',
                    'WWW.......',
                    '.WWW......',
                    'KKKKKKKKKK',
                    '....WWW...',
                    '.....WWW..',
                    '......WWW.',
                    '.......WWW',
                    '........WW',
                    '.........W',
                ],
                colors,
            ),
            'easy',
            'partly',
        ],
        [
            // The white inside the ring is its hole, closed in by black.
            'dark ring filled white',
            draw(
                [
                    '..........',
                    '..........',
                    '..KKKKKK..',
                    '..KWWWWK..',
                    '..KWWWWK..',
                    '..KWWWWK..',
                    '..KWWWWK..',
                    '..KKKKKK..',
                    '..........',
                    '..........',
                ],
                colors,
            ),
            'easy',
            'easy',
        ],
        [
            // The brackets surround the letter but don't touch it, so they aren't its backing.
            'light-gray brackets around a black letter',
            draw(
                [
                    '..........',
                    '.GG....GG.',
                    '.G......G.',
                    '.G..KK..G.',
                    'G..K..K..G',
                    'G..KKKK..G',
                    '.G.K..K.G.',
                    '.G......G.',
                    '.GG....GG.',
                    '..........',
                ],
                colors,
            ),
            'partly',
            'partly',
        ],
        [
            'logo on a white badge, beside a light-gray part',
            draw(
                [
                    '.WWWW.....',
                    'WWKKWW....',
                    'WKKKKW....',
                    'WWKKWW....',
                    '.WWWW.....',
                    '..........',
                    '..........',
                    '.......GGG',
                    '.......GGG',
                    '..........',
                ],
                colors,
            ),
            'partly',
            'easy',
        ],
        [
            // The orange square is 2.5:1 on the light background, but it is the figure's own.
            'white figure on its own orange square',
            draw(
                [
                    'OOOOOOOOOO',
                    'OOOOOOOOOO',
                    'OOWWWWWWOO',
                    'OWWWWWWWWO',
                    'OWWWWWWWWO',
                    'OWWWWWWWWO',
                    'OOWOOOWOOO',
                    'OOWOOOWOOO',
                    'OOOOOOOOOO',
                    'OOOOOOOOOO',
                ],
                { ...colors, O: [212, 133, 47, 255] },
            ),
            'easy',
            'easy',
        ],
        [
            // A white logo made for dark backgrounds, with a soft dark glow: the logo vanishes in light
            // mode and only the faint glow is left.
            'white logo with a 30% black glow',
            draw(
                [
                    '..........',
                    '.gggggggg.',
                    '.gWWWWWWg.',
                    '.gWWWWWWg.',
                    '.gWWWWWWg.',
                    '.gWWWWWWg.',
                    '.gWWWWWWg.',
                    '.gWWWWWWg.',
                    '.gggggggg.',
                    '..........',
                ],
                { ...colors, g: [0, 0, 0, 77] },
            ),
            'wcag',
            'easy',
        ],
        [
            'photo of two halves with rounded corners',
            draw(
                [
                    '.KKKKKKKK.',
                    ...Array(4).fill('KKKKKKKKKK'),
                    ...Array(4).fill('GGGGGGGGGG'),
                    '.GGGGGGGG.',
                ],
                colors,
            ),
            'easy',
            'easy',
        ],
        [
            // 3% is 3 pixels here.
            'light-gray part under 3%',
            draw(
                ['KKK.......', ...Array(8).fill('..........'), '........GG'],
                colors,
            ),
            'easy',
            'hard',
        ],
        [
            // Corners touching still join pixels into one part.
            'light-gray part of 3%, joined at the corners',
            draw(
                [
                    'KKK.......',
                    ...Array(6).fill('..........'),
                    '.......G..',
                    '........G.',
                    '.........G',
                ],
                colors,
            ),
            'partly',
            'partly',
        ],
        [
            'light-gray part under half opaque',
            draw(
                ['KKK.......', ...Array(8).fill('..........'), '.......ggg'],
                colors,
            ),
            'easy',
            'partly',
        ],
    ];

    for (const [name, pixels, inLight, inDark] of cases) {
        assert.equal(visibility(pixels, light), inLight, `${name}, light`);
        assert.equal(visibility(pixels, dark), inDark, `${name}, dark`);
    }
});

await test('shrinks an image by its most opaque pixels, so thin lines keep their color', () => {
    // 4×4 shrunk by 2: a one-pixel dark line in the top-left block, a translucent area in the
    // top-right, nothing below.
    const fine = new Uint8ClampedArray(64);
    fine.set([80, 80, 80, 255], 0);
    fine.set([0, 0, 0, 77], 3 * 4);
    fine.set([0, 0, 0, 40], 2 * 4);
    assert.deepEqual(
        [...mostOpaque(fine, 2)],
        [80, 80, 80, 255, 0, 0, 0, 77, 0, 0, 0, 0, 0, 0, 0, 0],
    );
});

await test('preview tiles get the regular light and dark backgrounds, never an event theme', () => {
    const scoped = [
        ...readFileSync(
            new URL('../../resources/css/app.css', import.meta.url),
            'utf8',
        ).matchAll(/([^{}]*data-theme-scope[^{}]*)\{([^{}]*)\}/g),
    ];
    assert.deepEqual(
        scoped.map(([, , body]) => /--muted:\s*([^;]+);/.exec(body)?.[1]),
        [light, dark],
    );
    assert.doesNotMatch(
        scoped.map(([, selector]) => selector).join(),
        /data-event-theme/,
    );
});
