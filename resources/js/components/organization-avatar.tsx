import type { CSSProperties } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { sizedAvatarUrl } from '@/lib/urls';
import { cn } from '@/lib/utils';
import type { Organization } from '@/types';

/**
 * Organizations are square, like on GitHub, so they never pass for a person.
 */
export function OrganizationAvatar({
    organization,
    className,
    style,
    pixels = 80,
}: {
    organization: Pick<Organization, 'name' | 'avatar_url'>;
    className?: string;
    style?: CSSProperties;
    /**
     * The image size to fetch: twice the largest size the avatar is drawn at, for high-density screens.
     */
    pixels?: number;
}) {
    const getInitials = useInitials();

    return (
        <Avatar
            className={cn(
                'size-8 overflow-hidden rounded-md border bg-muted',
                className,
            )}
            style={style}
        >
            <AvatarImage
                src={
                    organization.avatar_url === null
                        ? undefined
                        : sizedAvatarUrl(organization.avatar_url, pixels)
                }
                alt={organization.name}
            />
            <AvatarFallback className="rounded-none bg-muted font-mono text-[0.8em] text-muted-foreground">
                {getInitials(organization.name)}
            </AvatarFallback>
        </Avatar>
    );
}
