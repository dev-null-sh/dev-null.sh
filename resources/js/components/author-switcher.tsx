import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { OrganizationAvatar } from '@/components/organization-avatar';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';
import type { OrganizationAuthor, User } from '@/types';

/**
 * Who a composer posts as. An organization they picked but can no longer post as (removed from it since) gives way to
 * themselves, so a refused submission isn't sent as it again. `picked` is what they chose, gone or not.
 */
export function useAuthor() {
    const { auth } = usePage().props;
    const [picked, setAuthor] = useState<OrganizationAuthor | null>(null);
    const author =
        auth.organizations.find(({ id }) => id === picked?.id) ?? null;

    return { author, picked, setAuthor };
}

/**
 * The avatar of a composer, which members of an organization open to post as it instead of themselves. Everyone else
 * just sees their own.
 */
export function AuthorSwitcher({
    user,
    author,
    onChange,
    children,
    fallback,
    className,
}: {
    user: User;
    author: OrganizationAuthor | null;
    onChange: (author: OrganizationAuthor | null) => void;
    children: ReactNode;
    fallback: ReactNode;
    className?: string;
}) {
    const { auth } = usePage().props;

    if (auth.organizations.length === 0) {
        return fallback;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                aria-label={`Posting as @${(author ?? user).handle}. Choose who to post as`}
                className={cn('relative flex rounded-full', className)}
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
