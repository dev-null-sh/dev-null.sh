import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { OrganizationAvatar } from '@/components/organization-avatar';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserAvatar } from '@/components/user-avatar';
import type { OrganizationAuthor, User } from '@/types';

/**
 * The avatar of a composer, which members of an organization open to post as it instead of themselves, once posting as
 * an organization is switched on. Everyone else just sees their own.
 */
export function AuthorSwitcher({
    user,
    author,
    onChange,
    children,
    fallback,
}: {
    user: User;
    author: OrganizationAuthor | null;
    onChange: (author: OrganizationAuthor | null) => void;
    children: ReactNode;
    fallback: ReactNode;
}) {
    const { auth } = usePage().props;

    if (!auth.canPostAsOrganization || auth.organizations.length === 0) {
        return fallback;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                aria-label={`Posting as @${(author ?? user).handle}. Choose who to post as`}
                className="relative flex rounded-full"
                data-test="author-switcher"
            >
                {children}
                <span className="absolute -right-0.5 -bottom-0.5 flex size-4 items-center justify-center rounded-full border bg-card text-muted-foreground">
                    <ChevronDown aria-hidden className="size-3" />
                </span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-64">
                <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                    Post as
                </DropdownMenuLabel>
                <DropdownMenuCheckboxItem
                    checked={author === null}
                    onSelect={() => onChange(null)}
                >
                    <UserAvatar user={user} className="size-6" />
                    <Identity name={user.name} handle={user.handle} />
                </DropdownMenuCheckboxItem>
                {auth.organizations.map((organization) => (
                    <DropdownMenuCheckboxItem
                        key={organization.id}
                        checked={author?.id === organization.id}
                        onSelect={() => onChange(organization)}
                    >
                        <OrganizationAvatar
                            organization={organization}
                            className="size-6"
                        />
                        <Identity
                            name={organization.name}
                            handle={organization.handle}
                        />
                    </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function Identity({ name, handle }: { name: string; handle: string }) {
    return (
        <span className="flex min-w-0 flex-col">
            <span className="truncate font-medium">{name}</span>
            <span className="truncate font-mono text-xs text-muted-foreground">
                @{handle}
            </span>
        </span>
    );
}
