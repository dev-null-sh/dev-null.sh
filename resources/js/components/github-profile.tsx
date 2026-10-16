import { usePage } from '@inertiajs/react';
import { UserAvatar } from '@/components/user-avatar';

export function GitHubProfile() {
    const { auth } = usePage().props;

    const fields = [
        { label: 'name', value: auth.user.name },
        { label: 'username', value: `@${auth.user.username}` },
        { label: 'bio', value: auth.user.bio },
        { label: 'company', value: auth.user.company },
        { label: 'location', value: auth.user.location },
        { label: 'website', value: auth.user.website },
        {
            label: 'sponsors',
            value: auth.user.has_sponsors_listing
                ? `github.com/sponsors/${auth.user.username}`
                : null,
        },
    ];

    return (
        <>
            <div className="flex items-center gap-4">
                <UserAvatar user={auth.user} className="size-14" />
                <div className="min-w-0">
                    <p className="truncate font-semibold">{auth.user.name}</p>
                    <p className="truncate font-mono text-sm text-muted-foreground">
                        @{auth.user.username}
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
                                    ? 'break-words'
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
