import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { setRenderedAt } from '@/hooks/use-now';
import '../css/app.css';

// The card other sites frame: only its own page, none of the app's live updates, analytics or service worker.
void createInertiaApp({
    title: () => '/dev/null',
    resolve: (name) =>
        resolvePageComponent<ComponentType>(
            `./pages/${name}.tsx`,
            import.meta.glob<ComponentType>('./pages/embed/*.tsx'),
        ),
    setup({ el, App, props }) {
        // Like the app: relative times count from the server's render while the page hydrates.
        setRenderedAt(props.initialPage.props.now as number);

        createRoot(el).render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );
    },
    progress: false,
});
