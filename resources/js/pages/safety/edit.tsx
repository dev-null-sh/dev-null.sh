import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, Link } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import UserBlockController from '@/actions/App/Http/Controllers/UserBlockController';
import UserMuteController from '@/actions/App/Http/Controllers/UserMuteController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { laneColor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { User } from '@/types';

function PeopleList({
    people,
    empty,
    undo,
}: {
    people: User[];
    empty: string;
    undo: (person: User) => {
        label: string;
        href: ReturnType<typeof UserMuteController.destroy>;
    };
}) {
    if (people.length === 0) {
        return (
            <p className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                {empty}
            </p>
        );
    }

    return (
        <ul className="divide-y rounded-lg border">
            {people.map((person) => {
                const action = undo(person);

                return (
                    <li
                        key={person.id}
                        className="flex items-center gap-3 px-4 py-3"
                    >
                        <Link
                            href={show(person)}
                            className="flex min-w-0 flex-1 items-center gap-3"
                        >
                            <UserAvatar
                                user={person}
                                className="size-9 ring-2 ring-(--lane) ring-offset-2 ring-offset-card"
                                style={
                                    {
                                        '--lane': laneColor(person.lane),
                                    } as CSSProperties
                                }
                            />
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {person.name}
                                </p>
                                <p className="truncate font-mono text-[13px] text-muted-foreground">
                                    @{person.username}
                                </p>
                            </div>
                        </Link>
                        <Button
                            variant="outline"
                            size="sm"
                            className="shrink-0"
                            asChild
                        >
                            <Link
                                href={action.href}
                                as="button"
                                preserveScroll
                                aria-label={`${action.label} @${person.username}`}
                            >
                                {action.label}
                            </Link>
                        </Button>
                    </li>
                );
            })}
        </ul>
    );
}

export default function Edit({
    muted,
    blocked,
}: {
    muted: User[];
    blocked: User[];
}) {
    const { __ } = useLang();

    return (
        <>
            <Head title={__('settings.safety.title')} />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title={__('settings.safety.muted')}
                    description={__('settings.safety.muted_description')}
                />
                <PeopleList
                    people={muted}
                    empty={__('settings.safety.muted_empty')}
                    undo={(person) => ({
                        label: __('settings.safety.unmute'),
                        href: UserMuteController.destroy(person),
                    })}
                />
            </section>

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title={__('settings.safety.blocked')}
                    description={__('settings.safety.blocked_description')}
                />
                <PeopleList
                    people={blocked}
                    empty={__('settings.safety.blocked_empty')}
                    undo={(person) => ({
                        label: __('settings.safety.unblock'),
                        href: UserBlockController.destroy(person),
                    })}
                />
            </section>
        </>
    );
}

Edit.layout = [AppLayout, SettingsLayout];
