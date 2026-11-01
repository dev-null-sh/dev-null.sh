import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import ReactDOMServer from 'react-dom/server';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { setRenderedAt } from '@/hooks/use-now';
import { pageTitle } from '@/lib/page-title';
import { clockOf, setReaderClock } from '@/lib/utils';
import type { SharedClock } from '@/lib/utils';

createServer((page) =>
    createInertiaApp({
        page,
        // Right before rendering, which runs at once: another page set up meanwhile would set a clock of its own.
        render: (app) => {
            setReaderClock(
                clockOf(page.props.clock as SharedClock | undefined),
            );
            setRenderedAt(page.props.now as number);

            return ReactDOMServer.renderToString(app);
        },
        title: pageTitle,
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.tsx`,
                import.meta.glob<ComponentType>('./pages/**/*.tsx'),
            ),
        setup: ({ App, props }) => {
            return (
                <TooltipProvider delayDuration={150}>
                    <App {...props} />
                    <Toaster />
                </TooltipProvider>
            );
        },
    }),
);
