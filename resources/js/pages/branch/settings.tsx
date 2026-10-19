import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { BadgeCheck, ChevronLeft, GitBranch } from 'lucide-react';
import { type CSSProperties, useEffect, useState } from 'react';
import { toast } from 'sonner';
import BranchController from '@/actions/App/Http/Controllers/BranchController';
import BranchDescriptionController from '@/actions/App/Http/Controllers/BranchDescriptionController';
import BranchMemberController from '@/actions/App/Http/Controllers/BranchMemberController';
import BranchTransferController from '@/actions/App/Http/Controllers/BranchTransferController';
import BranchVerificationController from '@/actions/App/Http/Controllers/BranchVerificationController';
import { BranchMemberPicker } from '@/components/branch-member-picker';
import { CopyableCode } from '@/components/copyable-code';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { VerifiedDomain } from '@/components/verified-domain';
import AppLayout from '@/layouts/app-layout';
import { laneColor } from '@/lib/utils';
import { show as userShow } from '@/routes/user';
import type { User } from '@/types';

type BranchMember = {
    role: 'maintainer' | 'contributor';
    user: Pick<User, 'id' | 'username' | 'name' | 'avatar_url' | 'lane'>;
};

type BranchPayload = {
    name: string;
    description: string | null;
    domain: string | null;
    verified_domain: string | null;
    is_verified: boolean;
    verification_pending: boolean;
    dns_matched: boolean;
    can_verify: boolean;
    org_locked: boolean;
    verification_host: string | null;
    verification_record: string | null;
    viewer_role: 'maintainer' | 'contributor' | null;
    members: BranchMember[];
};

function savedToast(message: string) {
    toast(<span className="font-mono text-[13px]">{message}</span>);
}

function MemberRow({
    branchName,
    member,
    viewerUsername,
}: {
    branchName: string;
    member: BranchMember;
    viewerUsername: string | undefined;
}) {
    const [role, setRole] = useState(member.role);
    const lane = laneColor(member.user.lane);
    const isSelf = member.user.username === viewerUsername;

    return (
        <li
            className="flex flex-col gap-3 border-b p-3 last:border-b-0 sm:flex-row sm:items-center sm:gap-4"
            style={{ '--lane': lane } as CSSProperties}
        >
            <Link
                href={userShow(member.user.username)}
                className="flex min-w-0 flex-1 items-center gap-3 hover:opacity-90"
            >
                <UserAvatar
                    user={member.user}
                    className="size-9 shrink-0"
                />
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold">
                        {member.user.name}
                    </p>
                    <p className="flex items-center gap-1.5 font-mono text-[13px] text-muted-foreground">
                        <span
                            aria-hidden
                            className="size-2 shrink-0 rounded-full bg-(--lane)"
                        />
                        <span className="truncate">
                            @{member.user.username}
                        </span>
                    </p>
                </div>
            </Link>
            <div className="flex flex-wrap items-center gap-2 sm:shrink-0">
                <Form
                    {...BranchMemberController.update.form([
                        branchName,
                        member.user.username,
                    ])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => savedToast('Role updated')}
                    className="flex items-center gap-2"
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-col gap-1.5">
                            <div className="flex items-center gap-2">
                                <input type="hidden" name="role" value={role} />
                                <Select
                                    value={role}
                                    onValueChange={(value) =>
                                        setRole(
                                            value as BranchMember['role'],
                                        )
                                    }
                                    disabled={processing}
                                >
                                    <SelectTrigger
                                        size="sm"
                                        className="min-w-32"
                                        aria-label={`Role for @${member.user.username}`}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="maintainer">
                                            Maintainer
                                        </SelectItem>
                                        <SelectItem value="contributor">
                                            Contributor
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing || role === member.role}
                                    aria-busy={processing}
                                >
                                    {processing ? 'Saving…' : 'Save'}
                                </Button>
                            </div>
                            {errors.role && (
                                <p className="text-sm text-destructive">
                                    {errors.role}
                                </p>
                            )}
                        </div>
                    )}
                </Form>
                <Form
                    {...BranchMemberController.destroy.form([
                        branchName,
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
                            aria-busy={processing}
                        >
                            {isSelf ? 'Leave' : 'Remove'}
                        </Button>
                    )}
                </Form>
            </div>
        </li>
    );
}

export default function Settings({ branch }: { branch: BranchPayload }) {
    const { auth } = usePage().props;
    const viewerUsername = auth.user?.username;
    const canManage = branch.viewer_role === 'maintainer';
    const checkingDns =
        branch.verification_pending && !branch.dns_matched && !branch.is_verified;
    const transferCandidates = branch.members.filter(
        (member) => member.user.username !== viewerUsername,
    );
    const [transferOpen, setTransferOpen] = useState(false);
    const [transferUsername, setTransferUsername] = useState(
        transferCandidates[0]?.user.username ?? '',
    );
    const [descriptionLength, setDescriptionLength] = useState(
        branch.description?.length ?? 0,
    );
    const [pickedUsername, setPickedUsername] = useState<string | null>(null);
    const [memberPickerKey, setMemberPickerKey] = useState(0);

    useEffect(() => {
        if (!checkingDns) {
            return;
        }

        const id = window.setInterval(() => {
            router.reload({ only: ['branch'], preserveScroll: true });
        }, 5000);

        return () => window.clearInterval(id);
    }, [checkingDns, branch.name]);

    return (
        <>
            <Head title={`Manage #${branch.name}`} />

            <header className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <Link
                    href={BranchController.show(branch.name)}
                    aria-label={`Back to #${branch.name}`}
                    className="-ml-2 grid size-9 shrink-0 place-items-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    <ChevronLeft className="size-5" />
                </Link>
                <h1 className="flex min-w-0 items-center gap-1.5 font-semibold">
                    <GitBranch
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden
                    />
                    <span className="truncate">Manage #{branch.name}</span>
                </h1>
            </header>

            <div className="divide-y">
                <section className="px-4 py-6 sm:px-6">
                    <Heading
                        title="Description"
                        description="Shown on the branch page under the name. Keep it short."
                    />
                    <Form
                        {...BranchDescriptionController.update.form(
                            branch.name,
                        )}
                        options={{ preserveScroll: true }}
                        onSuccess={() => savedToast('Description saved')}
                        className="max-w-xl space-y-3"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <textarea
                                    name="description"
                                    defaultValue={branch.description ?? ''}
                                    maxLength={280}
                                    rows={3}
                                    disabled={processing}
                                    onChange={(event) =>
                                        setDescriptionLength(
                                            event.target.value.length,
                                        )
                                    }
                                    className="w-full rounded-md border bg-transparent px-3 py-2 text-sm disabled:opacity-60"
                                    placeholder="What this branch is for"
                                />
                                <div className="flex items-center justify-between gap-3">
                                    <p className="font-mono text-[12px] text-muted-foreground">
                                        {descriptionLength}/280
                                    </p>
                                    {errors.description && (
                                        <p className="text-sm text-destructive">
                                            {errors.description}
                                        </p>
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        aria-busy={processing}
                                        className="gap-1.5"
                                    >
                                        {processing && <Spinner />}
                                        {processing
                                            ? 'Saving…'
                                            : 'Save description'}
                                    </Button>
                                    {recentlySuccessful && !processing && (
                                        <p
                                            className="text-sm text-muted-foreground"
                                            role="status"
                                        >
                                            Saved
                                        </p>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                {canManage ? (
                    <>
                        <section className="px-4 py-6 sm:px-6">
                            <Heading
                                title="Domain verification"
                                description="Prove you control a domain with a DNS TXT record. We'll keep checking after you request — you can leave this page."
                            />

                            <div className="mb-5">
                                {branch.is_verified && branch.verified_domain ? (
                                    <VerifiedDomain domain={branch.verified_domain} />
                                ) : checkingDns ? (
                                    <p className="inline-flex items-center gap-1.5 rounded-md border border-sky-500/30 bg-sky-500/8 px-2.5 py-1.5 font-mono text-[13px] text-sky-700 dark:text-sky-400">
                                        <Spinner className="size-3.5 shrink-0" />
                                        Checking DNS… you can leave this page
                                    </p>
                                ) : branch.verification_pending ? (
                                    <p className="inline-flex items-center gap-1.5 rounded-md border border-amber-500/30 bg-amber-500/8 px-2.5 py-1.5 font-mono text-[13px] text-amber-700 dark:text-amber-400">
                                        <BadgeCheck
                                            className="size-3.5 shrink-0"
                                            aria-hidden
                                        />
                                        DNS matched — waiting for review
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Not verified yet.
                                    </p>
                                )}
                            </div>

                            <Form
                                {...BranchVerificationController.update.form(
                                    branch.name,
                                )}
                                options={{ preserveScroll: true }}
                                onSuccess={() => savedToast('Domain updated')}
                                className="max-w-xl space-y-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                            <input
                                                name="domain"
                                                defaultValue={branch.domain ?? ''}
                                                placeholder="laravel.com"
                                                required
                                                disabled={processing}
                                                className="h-9 w-full rounded-md border bg-transparent px-3 font-mono text-sm sm:max-w-xs disabled:opacity-60"
                                            />
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                                aria-busy={processing}
                                                className="gap-1.5 sm:shrink-0"
                                            >
                                                {processing && <Spinner />}
                                                {processing
                                                    ? 'Saving…'
                                                    : 'Set domain'}
                                            </Button>
                                        </div>
                                        {errors.domain && (
                                            <p className="text-sm text-destructive">
                                                {errors.domain}
                                            </p>
                                        )}
                                    </>
                                )}
                            </Form>

                            {branch.verification_host &&
                                branch.verification_record &&
                                !branch.is_verified && (
                                    <div className="mt-5 max-w-xl space-y-4">
                                        <p className="text-sm text-muted-foreground">
                                            Add this TXT record, then request
                                            verification. We'll keep checking
                                            until it matches.
                                        </p>
                                        <div className="space-y-3">
                                            <div className="space-y-1.5">
                                                <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                                    Host
                                                </p>
                                                <CopyableCode
                                                    code={branch.verification_host}
                                                    label="host"
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                                    TXT value
                                                </p>
                                                <CopyableCode
                                                    code={branch.verification_record}
                                                    label="TXT record"
                                                />
                                            </div>
                                        </div>
                                        {!branch.verification_pending &&
                                            branch.can_verify && (
                                                <Form
                                                    {...BranchVerificationController.store.form(
                                                        branch.name,
                                                    )}
                                                    options={{ preserveScroll: true }}
                                                    onSuccess={() =>
                                                        savedToast(
                                                            "We'll keep checking DNS — you can leave this page",
                                                        )
                                                    }
                                                >
                                                    {({ processing, errors }) => (
                                                        <div className="space-y-2">
                                                            <Button
                                                                type="submit"
                                                                disabled={processing}
                                                                aria-busy={processing}
                                                                className="gap-1.5"
                                                            >
                                                                {processing && (
                                                                    <Spinner />
                                                                )}
                                                                {processing
                                                                    ? 'Requesting…'
                                                                    : 'Request verification'}
                                                            </Button>
                                                            {errors.domain && (
                                                                <p className="text-sm text-destructive">
                                                                    {errors.domain}
                                                                </p>
                                                            )}
                                                        </div>
                                                    )}
                                                </Form>
                                            )}
                                        {!branch.verification_pending &&
                                            !branch.can_verify &&
                                            branch.org_locked && (
                                                <p className="text-sm text-muted-foreground">
                                                    This branch matches a Remotes org.
                                                    Only that org&apos;s maintainers can
                                                    request verification.
                                                </p>
                                            )}
                                    </div>
                                )}
                        </section>

                        <section className="px-4 py-6 sm:px-6">
                            <Heading
                                title="Members"
                                description="Invite up to 15 people from your follow graph. Contributors can edit the description; only maintainers manage the team, domain, and ownership. Anyone can still commit here."
                            />

                            <ul className="mb-5 overflow-hidden rounded-lg border">
                                {branch.members.map((member) => (
                                    <MemberRow
                                        key={member.user.id}
                                        branchName={branch.name}
                                        member={member}
                                        viewerUsername={viewerUsername}
                                    />
                                ))}
                            </ul>

                            <Form
                                {...BranchMemberController.store.form(branch.name)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => {
                                    savedToast('Member added');
                                    setPickedUsername(null);
                                    setMemberPickerKey((key) => key + 1);
                                }}
                                className="max-w-xl space-y-3"
                            >
                                {({ processing, errors }) => {
                                    const memberErrors = [
                                        errors.members,
                                        ...Object.entries(errors)
                                            .filter(([key]) =>
                                                key.startsWith('members.'),
                                            )
                                            .map(([, message]) => message),
                                    ].filter(Boolean);

                                    return (
                                        <>
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                                <BranchMemberPicker
                                                    key={memberPickerKey}
                                                    branch={branch.name}
                                                    disabled={processing}
                                                    onPickedChange={
                                                        setPickedUsername
                                                    }
                                                />
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        processing ||
                                                        pickedUsername === null
                                                    }
                                                    aria-busy={processing}
                                                    className="gap-1.5 sm:shrink-0"
                                                >
                                                    {processing && <Spinner />}
                                                    {processing
                                                        ? 'Adding…'
                                                        : 'Add member'}
                                                </Button>
                                            </div>
                                            {memberErrors.length > 0 && (
                                                <div className="space-y-1">
                                                    {memberErrors.map(
                                                        (message) => (
                                                            <p
                                                                key={String(
                                                                    message,
                                                                )}
                                                                className="text-sm text-destructive"
                                                            >
                                                                {message}
                                                            </p>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </>
                                    );
                                }}
                            </Form>

                            {transferCandidates.length > 0 && (
                                <div className="mt-8 max-w-xl space-y-3 border-t pt-6">
                                    <h3 className="text-sm font-semibold">
                                        Transfer ownership
                                    </h3>
                                    <p className="text-sm text-pretty text-muted-foreground">
                                        Hand maintainer access to another member. You
                                        stay on as a contributor. Verified status stays
                                        with the branch.
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setTransferUsername(
                                                transferCandidates[0]?.user.username ??
                                                '',
                                            );
                                            setTransferOpen(true);
                                        }}
                                        data-test="branch-transfer-open"
                                    >
                                        Transfer ownership
                                    </Button>
                                </div>
                            )}
                        </section>
                    </>
                ) : viewerUsername ? (
                    <section className="px-4 py-6 sm:px-6">
                        <Heading
                            title="Leave branch"
                            description="You stay listed as a contributor until you leave."
                        />
                        <Form
                            {...BranchMemberController.destroy.form([
                                branch.name,
                                viewerUsername,
                            ])}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                    aria-busy={processing}
                                >
                                    {processing ? 'Leaving…' : 'Leave branch'}
                                </Button>
                            )}
                        </Form>
                    </section>
                ) : null}
            </div>

            <Dialog open={transferOpen} onOpenChange={setTransferOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Transfer ownership?</DialogTitle>
                        <DialogDescription>
                            The person you pick becomes a maintainer. You stay
                            on #{branch.name} as a contributor. Verified domain
                            status stays with the branch.
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...BranchTransferController.store.form(branch.name)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            setTransferOpen(false);
                            savedToast('Ownership transferred');
                        }}
                        className="space-y-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <select
                                    name="username"
                                    value={transferUsername}
                                    onChange={(event) =>
                                        setTransferUsername(event.target.value)
                                    }
                                    className="h-9 w-full rounded-md border bg-transparent px-2 font-mono text-sm"
                                    required
                                >
                                    {transferCandidates.map((member) => (
                                        <option
                                            key={member.user.id}
                                            value={member.user.username}
                                        >
                                            @{member.user.username} (
                                            {member.role})
                                        </option>
                                    ))}
                                </select>
                                {errors.username && (
                                    <p className="text-sm text-destructive">
                                        {errors.username}
                                    </p>
                                )}
                                {errors.member && (
                                    <p className="text-sm text-destructive">
                                        {errors.member}
                                    </p>
                                )}
                                <DialogFooter className="gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setTransferOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            transferUsername === ''
                                        }
                                        data-test="branch-transfer-submit"
                                    >
                                        Transfer
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Settings.layout = AppLayout;
