import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BookOpen,
    CircleAlert,
    Gauge,
    KeyRound,
    ListOrdered,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import ApiDocsController from '@/actions/App/Http/Controllers/ApiDocsController';
import LogController from '@/actions/App/Http/Controllers/LogController';
import ConsoleLayout from '@/layouts/console/layout';
import type { ConsoleNavGroup } from '@/layouts/console/layout';
import { home } from '@/routes';
import type { ApiDocsProps, ApiDocsSection } from '@/types';

const ICONS: Record<string, LucideIcon> = {
    introduction: BookOpen,
    authentication: KeyRound,
    'rate-limits': Gauge,
    pagination: ListOrdered,
    errors: CircleAlert,
};

function navItems(sections: ApiDocsSection[]) {
    return sections.map((section) => ({
        key: section.slug,
        title: section.title,
        description: section.description,
        href: ApiDocsController.show(section.slug),
        icon: ICONS[section.slug] ?? BookOpen,
    }));
}

/**
 * The API docs as a console beside the log, its guides in the sidebar.
 */
export default function ApiDocsLayout({ children }: PropsWithChildren) {
    const { props } = usePage<ApiDocsProps>();

    const groups: ConsoleNavGroup[] = [
        {
            label: 'Guide',
            items: navItems(props.sections),
        },
    ];

    return (
        <ConsoleLayout
            chip="api"
            label="/dev/null API docs, go to the introduction"
            navLabel="API docs"
            brandHref={ApiDocsController.show('introduction')}
            prefetch
            groups={groups}
            current={props.current}
            backHref={
                props.auth.log === null
                    ? home().url
                    : LogController.show(props.auth.log).url
            }
            user={props.auth.user}
            headerActions={
                <span className="px-2 font-mono text-[13px] text-muted-foreground">
                    v1
                </span>
            }
        >
            {children}
        </ConsoleLayout>
    );
}
