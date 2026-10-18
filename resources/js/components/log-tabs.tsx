import LogController from '@/actions/App/Http/Controllers/LogController';
import UserListController from '@/actions/App/Http/Controllers/UserListController';
import { PageTabs } from '@/components/page-tabs';
import type { Log, PinnedList } from '@/types';

/**
 * The logs, with the viewer's pinned lists after them, where the active tab is a log or a list's id.
 */
export function LogTabs({
    active,
    pinnedLists,
}: {
    active: Log | string;
    pinnedLists: PinnedList[];
}) {
    return (
        <PageTabs
            label="Logs"
            prefetch={false}
            tabs={[
                {
                    title: 'Following',
                    href: LogController.show('following'),
                    isActive: active === 'following',
                },
                {
                    title: 'Latest',
                    href: LogController.show('latest'),
                    isActive: active === 'latest',
                },
                {
                    title: 'Trending',
                    href: LogController.show('trending'),
                    isActive: active === 'trending',
                },
                ...pinnedLists.map((list) => ({
                    key: list.id,
                    title: list.name,
                    href: UserListController.show(list.id),
                    isActive: active === list.id,
                })),
            ]}
        />
    );
}
