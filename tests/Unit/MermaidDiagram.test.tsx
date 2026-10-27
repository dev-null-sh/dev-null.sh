/// <reference types="node" />
import assert from 'node:assert/strict';
import { App } from '@inertiajs/react';
import type { ComponentProps, ReactElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { CommitBody } from '../../resources/js/components/commit-body';
import { MermaidDiagram } from '../../resources/js/components/mermaid-diagram';
import { bodySegments } from '../../resources/js/lib/code';
import { renderMermaid } from '../../resources/js/lib/mermaid-renderer';
import { mermaidTheme } from '../../resources/js/lib/mermaid-theme';

function renderPage(content: ReactElement): string {
    return renderToStaticMarkup(
        <App
            initialComponent={() => content}
            initialPage={
                {
                    component: 'Commit',
                    url: '/',
                    version: null,
                    props: { errors: {}, auth: { user: null, pasteis: null } },
                    clearHistory: false,
                    encryptHistory: false,
                } as unknown as ComponentProps<typeof App>['initialPage']
            }
        />,
    );
}

const source = 'flowchart LR\nA[<script>alert(1)</script>] --> B[okay]';
const segments = bodySegments('Before\n```MERMAID\n' + source + '\n```\nAfter');

test('preserves Mermaid source verbatim alongside an escaped fallback', () => {
    const mermaid = segments.find((segment) => segment.type === 'block');
    assert.ok(mermaid && mermaid.type === 'block');
    assert.equal(mermaid.language, 'MERMAID');
    assert.equal(mermaid.source, source);
    assert.ok(mermaid.html.includes('&lt;script&gt;'));
});

test('server-renders a native diagram placeholder without executing or injecting SVG', () => {
    const markup = renderPage(<CommitBody segments={segments} mentions={null} />);
    assert.ok(markup.includes('data-test="mermaid-diagram"'));
    assert.ok(markup.includes('Loading diagram'));
    assert.ok(!markup.includes('<script>'));
    // Lucide toolbar icons are SVG; the diagram itself must not be embedded in the HTML.
    assert.ok(!markup.includes('<foreignObject'));
    assert.ok(!markup.includes('src="blob:'));
    assert.ok(markup.includes('Show source'));
});

test('does not load diagrams in static excerpts or private Remotes', () => {
    for (const props of [{ isStatic: true }, { renderDiagrams: false }]) {
        const markup = renderPage(
            <CommitBody segments={segments} mentions={null} {...props} />,
        );
        assert.ok(!markup.includes('data-test="mermaid-diagram"'));
        assert.match(markup, /<pre[^>]*>flowchart LR/);
        assert.ok(!markup.includes('<script>'));
    }
});

test('uses the normal code block for other languages and unrecognized Mermaid source', () => {
    const markup = renderPage(
        <CommitBody
            segments={[
                { type: 'block', language: 'javascript', html: 'hello' },
                { type: 'block', language: 'mermaid', html: 'source was not provided' },
            ]}
            mentions={null}
        />,
    );

    assert.ok(!markup.includes('data-test="mermaid-diagram"'));
    assert.ok(markup.includes('source was not provided'));
});


test('shows long diagrams as escaped copyable source, without a dead view switch', () => {
    const oversized = '<svg onload=alert(1)>'.repeat(300);
    const markup = renderPage(<MermaidDiagram source={oversized} />);

    assert.ok(markup.includes('6,000-character limit'));
    assert.match(markup, /aria-label="Show diagram"[^>]*disabled=""/);
    assert.ok(markup.includes('aria-label="Copy Mermaid source"'));
    assert.ok(markup.includes('&lt;svg onload=alert(1)&gt;'));
    assert.ok(!markup.includes('<svg onload'));
});

test('uses the real dev-null theme colors rather than Mermaid defaults', () => {
    const dark = mermaidTheme('dark');
    const light = mermaidTheme('light');

    assert.equal(dark.background, '#18181a');
    assert.equal(dark.primaryBorderColor, '#7aa2f7');
    assert.equal(dark.secondaryBorderColor, '#51cf66');
    assert.equal(dark.tertiaryBorderColor, '#da77f2');
    assert.equal(dark.darkMode, true);
    assert.equal(dark.fontFamily, 'system-ui, sans-serif');
    assert.equal(light.background, '#ffffff');
    assert.equal(light.primaryBorderColor, '#3b6fe0');
    assert.equal(light.darkMode, false);

    for (const palette of [dark, light]) {
        assert.notEqual(palette.primaryColor, palette.secondaryColor);
        assert.notEqual(palette.secondaryColor, palette.tertiaryColor);
        assert.equal(new Set([
            palette.pie1,
            palette.pie2,
            palette.pie3,
            palette.pie4,
            palette.pie5,
        ]).size, 5);
    }
});

test('preserves diamond and hexagon syntax with both theme palettes', () => {
    const source = 'flowchart LR\nD{Decide?} --> H{{Prepare}}';
    const diagram = bodySegments('```mermaid\n' + source + '\n```')
        .find((segment) => segment.type === 'block');

    assert.ok(diagram && diagram.type === 'block');
    assert.equal(diagram.source, source);
    // No global polygon CSS: these shapes keep their base or explicit class colors.
    assert.equal(mermaidTheme('dark').primaryColor, '#202b40');
    assert.equal(mermaidTheme('light').primaryColor, '#e8effc');
});

test('rejects image and remote CSS sources before Mermaid parses them', async () => {
    const imageSources = [
        'flowchart LR\nA@{ img: "https://example.com/pixel.png" } --> B',
        'flowchart LR\nA@{\n img : "//example.com/pixel.png"\n}',
        'flowchart LR\nA@{ "i\\u006dg": "https://example.com/pixel.png" }',
        'flowchart LR\nA@{ x: "}", "i\\u006dg": "https://example.com/pixel.png" }',
        'flowchart LR\nA["<img src=https://example.com/pixel.png>"]',
        'flowchart LR\nA["![pixel](https://example.com/pixel.png)"]',
        'flowchart LR\nclassDef danger fill:url(https://example.com/pixel.png)',
    ];

    for (const source of imageSources) {
        await assert.rejects(
            renderMermaid(source, 'light'),
            /Embedded images are not supported/,
        );
    }
});
