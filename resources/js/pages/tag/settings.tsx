import { Form, Head, Link } from '@inertiajs/react';
import TagController from '@/actions/App/Http/Controllers/TagController';
import TagDescriptionController from '@/actions/App/Http/Controllers/TagDescriptionController';
import TagMemberController from '@/actions/App/Http/Controllers/TagMemberController';
import TagVerificationController from '@/actions/App/Http/Controllers/TagVerificationController';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { VerifiedDomain } from '@/components/verified-domain';
import AppLayout from '@/layouts/app-layout';
import type { User } from '@/types';

type TagMember = {
    role: 'maintainer' | 'contributor';
    user: Pick<User, 'id' | 'username' | 'name' | 'avatar_url' | 'lane'>;
};

type TagPayload = {
    name: string;
    description: string | null;
    domain: string | null;
    verified_domain: string | null;
    is_verified: boolean;
    verification_pending: boolean;
    can_verify: boolean;
    org_locked: boolean;
    verification_host: string | null;
    verification_record: string | null;
    members: TagMember[];
};

export default function Settings({ tag }: { tag: TagPayload }) {
    return (
        <>
            <Head title={`Manage #${tag.name}`} />

            <header className="sticky top-0 z-10 flex h-14 items-center justify-between gap-3 border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">Manage #{tag.name}</h1>
                <Button variant="outline" size="sm" asChild>
                    <Link href={TagController.show(tag.name)}>Back</Link>
                </Button>
            </header>

            <div className="space-y-8 px-4 py-6 sm:px-6">
                <section className="space-y-3">
                    <h2 className="font-semibold">Description</h2>
                    <Form
                        {...TagDescriptionController.update.form(tag.name)}
                        options={{ preserveScroll: true }}
                        className="space-y-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <textarea
                                    name="description"
                                    defaultValue={tag.description ?? ''}
                                    maxLength={280}
                                    rows={3}
                                    className="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                                    placeholder="What this hashtag is for"
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">
                                        {errors.description}
                                    </p>
                                )}
                                <Button type="submit" disabled={processing}>
                                    Save description
                                </Button>
                            </>
                        )}
                    </Form>
                </section>

                <section className="space-y-3">
                    <h2 className="font-semibold">Domain verification</h2>
                    {tag.is_verified && tag.verified_domain ? (
                        <VerifiedDomain domain={tag.verified_domain} />
                    ) : tag.verification_pending ? (
                        <p className="text-sm text-muted-foreground">
                            Verification requested — waiting for review.
                        </p>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Not verified yet.
                        </p>
                    )}
                    <Form
                        {...TagVerificationController.update.form(tag.name)}
                        options={{ preserveScroll: true }}
                        className="space-y-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    name="domain"
                                    defaultValue={tag.domain ?? ''}
                                    placeholder="laravel.com"
                                    required
                                    className="h-9 w-full max-w-sm rounded-md border bg-transparent px-3 font-mono text-sm"
                                />
                                {errors.domain && (
                                    <p className="text-sm text-destructive">
                                        {errors.domain}
                                    </p>
                                )}
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Set domain
                                </Button>
                            </>
                        )}
                    </Form>
                    {tag.verification_host &&
                        tag.verification_record &&
                        !tag.is_verified && (
                            <div className="space-y-2 rounded-md border p-3 font-mono text-[13px]">
                                <p>
                                    <span className="text-muted-foreground">
                                        Host:{' '}
                                    </span>
                                    {tag.verification_host}
                                </p>
                                <p className="break-all">
                                    <span className="text-muted-foreground">
                                        TXT:{' '}
                                    </span>
                                    {tag.verification_record}
                                </p>
                                {!tag.verification_pending &&
                                    tag.can_verify && (
                                        <Form
                                            {...TagVerificationController.store.form(
                                                tag.name,
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <Button
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        Request verification
                                                    </Button>
                                                    {errors.domain && (
                                                        <p className="mt-2 text-sm text-destructive">
                                                            {errors.domain}
                                                        </p>
                                                    )}
                                                </>
                                            )}
                                        </Form>
                                    )}
                                {!tag.verification_pending &&
                                    !tag.can_verify &&
                                    tag.org_locked && (
                                        <p className="text-sm text-muted-foreground">
                                            This branch matches a Remotes org.
                                            Only that org's maintainers can
                                            request verification.
                                        </p>
                                    )}
                            </div>
                        )}
                </section>

                <section className="space-y-3">
                    <h2 className="font-semibold">Members</h2>
                    <ul className="divide-y rounded-md border">
                        {tag.members.map((member) => (
                            <li
                                key={member.user.id}
                                className="flex flex-wrap items-center gap-3 p-3"
                            >
                                <UserAvatar
                                    user={member.user}
                                    className="size-8"
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold">
                                        {member.user.name}
                                    </p>
                                    <p className="font-mono text-[13px] text-muted-foreground">
                                        @{member.user.username}
                                    </p>
                                </div>
                                <Form
                                    {...TagMemberController.update.form([
                                        tag.name,
                                        member.user.username,
                                    ])}
                                    options={{ preserveScroll: true }}
                                    className="flex items-center gap-2"
                                >
                                    {({ processing }) => (
                                        <>
                                            <select
                                                name="role"
                                                defaultValue={member.role}
                                                className="h-9 rounded-md border bg-transparent px-2 text-sm"
                                            >
                                                <option value="maintainer">
                                                    maintainer
                                                </option>
                                                <option value="contributor">
                                                    contributor
                                                </option>
                                            </select>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                Save
                                            </Button>
                                        </>
                                    )}
                                </Form>
                                <Form
                                    {...TagMemberController.destroy.form([
                                        tag.name,
                                        member.user.username,
                                    ])}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </Form>
                            </li>
                        ))}
                    </ul>
                    <Form
                        {...TagMemberController.store.form(tag.name)}
                        options={{ preserveScroll: true }}
                        className="space-y-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    name="members[]"
                                    placeholder="username"
                                    required
                                    className="h-9 w-full max-w-sm rounded-md border bg-transparent px-3 font-mono text-sm"
                                />
                                {errors.members && (
                                    <p className="text-sm text-destructive">
                                        {errors.members}
                                    </p>
                                )}
                                <Button type="submit" disabled={processing}>
                                    Add member
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}

Settings.layout = AppLayout;
