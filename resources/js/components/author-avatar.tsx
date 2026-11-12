import type { CSSProperties } from 'react';
import { OrganizationAvatar } from '@/components/organization-avatar';
import { UserAvatar } from '@/components/user-avatar';
import type { CommitAuthor } from '@/types';

/**
 * The avatar of a commit's author: round for a person, square for an organization, like on GitHub.
 */
export function AuthorAvatar({
    author,
    ...props
}: {
    author: CommitAuthor;
    className?: string;
    style?: CSSProperties;
    pixels?: number;
}) {
    return author.type === 'organization' ? (
        <OrganizationAvatar organization={author} {...props} />
    ) : (
        <UserAvatar user={author} {...props} />
    );
}
