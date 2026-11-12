import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Bell,
    BookOpen,
    Bug,
    Building2,
    CalendarClock,
    CircleAlert,
    CircleUser,
    FileJson,
    Flame,
    Gauge,
    GitCommitHorizontal,
    KeyRound,
    ListOrdered,
    MessagesSquare,
    Search,
    Users,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { PropsWithChildren } from 'react';
import OpenApiController from '@/actions/App/Http/Controllers/Api/OpenApiController';
import ApiDocsController from '@/actions/App/Http/Controllers/ApiDocsController';
import LogController from '@/actions/App/Http/Controllers/LogController';
import {
    EndpointSearch,
    isSearchShortcut,
} from '@/components/api-docs/endpoint-search';
import ConsoleLayout from '@/layouts/console/layout';
import type { ConsoleNavGroup } from '@/layouts/console/layout';
import { useKeyLabels } from '@/lib/keyboard';
import { home } from '@/routes';
import type { ApiDocsProps, ApiDocsSection } from '@/types';

const ICONS: Record<string, LucideIcon> = {
    introduction: BookOpen,
    authentication: KeyRound,
    'rate-limits': Gauge,
    pagination: ListOrdered,
    errors: CircleAlert,
    users: Users,
    commits: GitCommitHorizontal,
    organizations: Building2,
    me: CircleUser,
    search: Search,
    'scheduled-commits': CalendarClock,
    remotes: MessagesSquare,
    notifications: Bell,
    headlines: Flame,
    issues: Bug,
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
 * The API docs as a console beside the log: the guides and the groups of the reference in the sidebar, and the spec
 * they are read from elsewhere.
 */
export default function ApiDocsLayout({ children }: PropsWithChildren) {
    const { props } = usePage<ApiDocsProps>();
    const keys = useKeyLabels();
    const [isSearching, setIsSearching] = useState(false);
    const searchButton = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (isSearchShortcut(event)) {
                event.preventDefault();
                setIsSearching(true);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const groups: ConsoleNavGroup[] = [
        {
            label: 'Guide',
            items: navItems(
                props.sections.filter((section) => section.kind === 'guide'),
            ),
        },
        {
            label: 'Reference',
            items: navItems(
                props.sections.filter(
                    (section) => section.kind === 'reference',
                ),
            ),
        },
        {
            label: 'Elsewhere',
            items: [
                {
                    key: 'openapi',
                    title: 'openapi.json',
                    description: 'The whole API as OpenAPI 3.1',
                    href: OpenApiController.show().url,
                    icon: FileJson,
                    external: true,
                },
            ],
        },
    ];

    return (
        <>
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
                    <>
                        <span className="px-2 font-mono text-[13px] text-muted-foreground">
                            v1
                        </span>
                        <button
                            ref={searchButton}
                            type="button"
                            onClick={() => setIsSearching(true)}
                            aria-label="Search the API docs"
                            aria-keyshortcuts="Meta+K Control+K /"
                            className="flex h-8 items-center gap-2 rounded-md border bg-background px-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground sm:w-56"
                        >
                            <Search aria-hidden className="size-4 shrink-0" />
                            <span className="hidden sm:inline">Search</span>
                            <kbd className="ml-auto hidden rounded-sm border px-1 font-mono text-[11px] sm:inline">
                                {keys.combo(keys.mod, 'K')}
                            </kbd>
                        </button>
                    </>
                }
            >
                {children}
            </ConsoleLayout>
            <EndpointSearch
                open={isSearching}
                onOpenChange={setIsSearching}
                sections={props.sections}
                index={props.index}
                current={props.current}
                returnFocus={() => searchButton.current?.focus()}
            />
        </>
    );
}
