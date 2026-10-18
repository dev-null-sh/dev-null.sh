import { Link, router } from '@inertiajs/react';
import { ListPlus } from 'lucide-react';
import { useState } from 'react';
import UserListController from '@/actions/App/Http/Controllers/UserListController';
import UserListMemberController from '@/actions/App/Http/Controllers/UserListMemberController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { visitWithErrorToast } from '@/lib/user-lists';
import type { ProfileList, User } from '@/types';

/**
 * The viewer's lists load once the menu opens, so profiles don't pay for them on every visit.
 */
export function UserListMenu({
    user,
    lists,
}: {
    user: Pick<User, 'username'>;
    lists: ProfileList[] | undefined;
}) {
    const [isSaving, setIsSaving] = useState(false);

    const load = (open: boolean) => {
        if (open) {
            router.reload({ only: ['lists'] });
        }
    };

    const toggle = (list: ProfileList) =>
        visitWithErrorToast(
            list.has_member
                ? UserListMemberController.destroy([list.id, user.username])
                : UserListMemberController.store([list.id, user.username]),
            {
                only: ['lists'],
                onStart: () => setIsSaving(true),
                onFinish: () => setIsSaving(false),
            },
        );

    return (
        <DropdownMenu modal={false} onOpenChange={load}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="icon"
                    className="shrink-0"
                    aria-label={`Add @${user.username} to a list`}
                    data-test="user-list-menu"
                >
                    <ListPlus />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>Lists</DropdownMenuLabel>
                {lists === undefined ? (
                    <DropdownMenuItem disabled>Loading…</DropdownMenuItem>
                ) : (
                    lists.map((list) => (
                        <DropdownMenuCheckboxItem
                            key={list.id}
                            checked={list.has_member}
                            disabled={isSaving}
                            onSelect={(event) => {
                                event.preventDefault();
                                toggle(list);
                            }}
                        >
                            <span className="truncate">{list.name}</span>
                        </DropdownMenuCheckboxItem>
                    ))
                )}
                {lists?.length === 0 && (
                    <p className="px-2 py-1.5 text-sm text-muted-foreground">
                        You don't have any lists yet.
                    </p>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={UserListController.index()}>Manage lists</Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
