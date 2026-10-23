import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import ReactDOMServer from 'react-dom/server';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { pageTitle } from '@/lib/page-title';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
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
