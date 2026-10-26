import { UserAvatar } from '@/components/user-avatar';
import type { User } from '@/types';

export function UserInfo({
    user,
    showUsername = false,
}: {
    user: User;
    showUsername?: boolean;
}) {
    return (
        <>
            <UserAvatar user={user} />
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showUsername && (
                    <span className="truncate text-xs text-muted-foreground">
                        @{user.handle}
                    </span>
                )}
            </div>
        </>
    );
}
