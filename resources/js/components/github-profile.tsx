import { UserAvatar } from '@/components/user-avatar';
import { useUser } from '@/hooks/use-user';

export function GitHubProfile() {
    const { user } = useUser();

    const fields = [
        { label: 'name', value: user.name },
        { label: 'username', value: `@${user.handle}` },
        { label: 'bio', value: user.bio },
        { label: 'company', value: user.company },
        { label: 'location', value: user.location },
        { label: 'website', value: user.website },
        {
            label: 'sponsors',
            value: user.has_sponsors_listing
                ? `github.com/sponsors/${user.handle}`
                : null,
        },
    ];

    return (
        <>
            <div className="flex items-center gap-4">
                <UserAvatar user={user} className="size-14" />
                <div className="min-w-0">
                    <p className="truncate font-semibold">{user.name}</p>
                    <p className="truncate font-mono text-sm text-muted-foreground">
                        @{user.handle}
                    </p>
                </div>
            </div>

            <dl className="mt-5 divide-y rounded-lg border text-sm">
                {fields.map((field) => (
                    <div
                        key={field.label}
                        className="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 px-4 py-2.5"
                    >
                        <dt className="font-mono text-[13px] text-muted-foreground">
                            {field.label}
                        </dt>
                        <dd
                            className={
                                field.value
                                    ? 'wrap-break-word'
                                    : 'font-mono text-[13px] text-muted-foreground'
                            }
                        >
                            {field.value ?? 'null'}
                        </dd>
                    </div>
                ))}
            </dl>
        </>
    );
}
