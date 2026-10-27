/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { TimestampTooltip } from '../../resources/js/components/timestamp-tooltip';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '../../resources/js/components/ui/tooltip';
import type { User } from '../../resources/js/types';

test('renders only its trigger until someone points at it or focuses it, so a page full of buttons stays light', () => {
    const markup = renderToStaticMarkup(
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button type="button">Star</button>
                </TooltipTrigger>
                <TooltipContent>Star this commit</TooltipContent>
            </Tooltip>
        </TooltipProvider>,
    );

    assert.equal(markup, '<button type="button">Star</button>');
});

test('mounts a tooltip shown on purpose at once, and leaves one held closed as its trigger alone', () => {
    const render = (open: boolean) =>
        renderToStaticMarkup(
            <TooltipProvider>
                <Tooltip open={open}>
                    <TooltipTrigger asChild>
                        <button type="button">Star</button>
                    </TooltipTrigger>
                    <TooltipContent>Star this commit</TooltipContent>
                </Tooltip>
            </TooltipProvider>,
        );

    assert.match(render(true), /data-state="(delayed|instant)-open"/);
    assert.equal(render(false), '<button type="button">Star</button>');
});

test('renders a timestamp alone until someone points at it', () => {
    const author = {
        id: 'author',
        name: 'Ada',
        lane: 1,
        timezone: null,
    } as User;
    const markup = renderToStaticMarkup(
        <TooltipProvider>
            <TimestampTooltip date="2026-10-24T12:00:00Z" author={author}>
                <time dateTime="2026-10-24T12:00:00Z">1h</time>
            </TimestampTooltip>
        </TooltipProvider>,
    );

    assert.equal(markup, '<time dateTime="2026-10-24T12:00:00Z">1h</time>');
});
