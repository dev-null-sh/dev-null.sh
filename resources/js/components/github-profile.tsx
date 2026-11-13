import { Form, router } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import UserProfileController from '@/actions/App/Http/Controllers/UserProfileController';
import InputError from '@/components/input-error';
import { UserAvatar } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useUser } from '@/hooks/use-user';

type UsernameCheck =
    | { status: 'idle' | 'checking' }
    | { status: 'available' | 'invalid' | 'error'; message: string };

function ProcessingReporter({
    processing,
    onChange,
}: {
    processing: boolean;
    onChange: (processing: boolean) => void;
}) {
    useEffect(() => onChange(processing), [onChange, processing]);

    return null;
}

export function GitHubProfile({
    showSaveButton = true,
    onProcessingChange,
    syncVersion = 0,
}: {
    showSaveButton?: boolean;
    onProcessingChange?: (processing: boolean) => void;
    syncVersion?: number;
}) {
    const { user } = useUser();
    const [devNullUsername, setDevNullUsername] = useState(user.dev_null_username ?? '');
    const [usernameCheck, setUsernameCheck] = useState<UsernameCheck>({ status: 'idle' });
    const profileFormKey = JSON.stringify([
        syncVersion,
        user.dev_null_username,
        user.name,
        user.bio,
        user.company,
        user.location,
        user.website,
        user.sponsors_url,
        user.avatar_url,
    ]);

    useEffect(() => {
        setDevNullUsername(user.dev_null_username ?? '');
    }, [user.dev_null_username]);

    useEffect(() => {
        const normalizedUsername = devNullUsername.trim().toLowerCase();

        if (!normalizedUsername || normalizedUsername === user.dev_null_username) {
            setUsernameCheck({ status: 'idle' });

            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            setUsernameCheck({ status: 'checking' });

            void fetch(
                UserProfileController.checkDevNullUsername.url({
                    query: { dev_null_username: normalizedUsername },
                }),
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            )
                .then(async (response) => {
                    const data = (await response.json()) as {
                        available?: boolean;
                        errors?: Record<string, string[]>;
                    };

                    if (controller.signal.aborted) {
                        return;
                    }

                    if (response.ok && data.available === true) {
                        setUsernameCheck({
                            status: 'available',
                            message: 'Username is available.',
                        });
                    } else if (response.status === 422) {
                        setUsernameCheck({
                            status: 'invalid',
                            message:
                                data.errors?.dev_null_username?.[0] ??
                                'This username is not available.',
                        });
                    } else {
                        setUsernameCheck({
                            status: 'error',
                            message: 'Could not check username. Try again.',
                        });
                    }
                })
                .catch(() => {
                    if (controller.signal.aborted) {
                        return;
                    }

                    setUsernameCheck({
                        status: 'error',
                        message: 'Could not check username. Try again.',
                    });
                });
        }, 300);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [devNullUsername, user.dev_null_username]);

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

            <Form
                {...UserProfileController.updateDetails.form()}
                key={profileFormKey}
                id="profile-details-form"
                options={{ preserveScroll: true }}
                onSuccess={() => router.flushAll()}
                className="mt-5 grid max-w-2xl gap-4 sm:grid-cols-2"
            >
                {({ errors, processing }) => (
                    <>
                        {onProcessingChange && (
                            <ProcessingReporter
                                processing={processing}
                                onChange={onProcessingChange}
                            />
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="github-username">
                                GitHub username
                            </Label>
                            <input
                                id="github-username"
                                value={user.handle}
                                disabled
                                readOnly
                                className="flex h-9 w-full min-w-0 rounded-md border border-input bg-muted px-3 py-1 text-base text-muted-foreground opacity-70 md:text-sm"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="dev-null-username">
                                Dev-Null username
                            </Label>
                            <input
                                id="dev-null-username"
                                name="dev_null_username"
                                value={devNullUsername}
                                onChange={(event) =>
                                    setDevNullUsername(event.currentTarget.value)
                                }
                                maxLength={39}
                                autoComplete="off"
                                aria-invalid={
                                    Boolean(errors.dev_null_username) ||
                                    usernameCheck.status === 'invalid'
                                }
                                aria-describedby={[
                                    errors.dev_null_username
                                        ? 'dev-null-username-error'
                                        : null,
                                    usernameCheck.status !== 'idle'
                                        ? 'dev-null-username-status'
                                        : null,
                                ]
                                    .filter((id): id is string => id !== null)
                                    .join(' ') || undefined}
                                placeholder="username"
                                className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                                data-test="dev-null-username-input"
                            />
                            <InputError
                                id="dev-null-username-error"
                                message={errors.dev_null_username}
                            />
                            {usernameCheck.status !== 'idle' && (
                                <p
                                    id="dev-null-username-status"
                                    role={
                                        usernameCheck.status === 'invalid' ||
                                        usernameCheck.status === 'error'
                                            ? 'alert'
                                            : 'status'
                                    }
                                    aria-live="polite"
                                    className={
                                        usernameCheck.status === 'available'
                                            ? 'text-sm text-green-600 dark:text-green-400'
                                            : usernameCheck.status === 'invalid' ||
                                                usernameCheck.status === 'error'
                                              ? 'text-sm text-destructive'
                                              : 'text-sm text-muted-foreground'
                                    }
                                >
                                    {usernameCheck.status === 'checking'
                                        ? 'Checking username...'
                                        : usernameCheck.message}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="profile-name">Name</Label>
                            <input
                                id="profile-name"
                                name="name"
                                defaultValue={user.name}
                                maxLength={255}
                                required
                                aria-invalid={Boolean(errors.name)}
                                className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="profile-bio">Bio</Label>
                            <textarea
                                id="profile-bio"
                                name="bio"
                                defaultValue={user.bio ?? ''}
                                maxLength={160}
                                rows={3}
                                aria-invalid={Boolean(errors.bio)}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                            />
                            <InputError message={errors.bio} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="profile-company">Company</Label>
                            <input
                                id="profile-company"
                                name="company"
                                defaultValue={user.company ?? ''}
                                maxLength={255}
                                aria-invalid={Boolean(errors.company)}
                                className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                            />
                            <InputError message={errors.company} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="profile-location">Location</Label>
                            <input
                                id="profile-location"
                                name="location"
                                defaultValue={user.location ?? ''}
                                maxLength={255}
                                aria-invalid={Boolean(errors.location)}
                                className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                            />
                            <InputError message={errors.location} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="profile-website">Website</Label>
                            <input
                                id="profile-website"
                                name="website"
                                type="url"
                                defaultValue={user.website ?? ''}
                                maxLength={255}
                                aria-invalid={Boolean(errors.website)}
                                className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                            />
                            <InputError message={errors.website} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="profile-sponsors-url">
                                Sponsors URL
                            </Label>
                            <input
                                id="profile-sponsors-url"
                                name="sponsors_url"
                                type="url"
                                defaultValue={user.sponsors_url ?? ''}
                                maxLength={2048}
                                aria-invalid={Boolean(errors.sponsors_url)}
                                className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                            />
                            <InputError message={errors.sponsors_url} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="profile-avatar">Profile image</Label>
                            <input
                                id="profile-avatar"
                                name="avatar"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                aria-invalid={Boolean(errors.avatar)}
                                className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium file:text-foreground"
                            />
                            <p className="text-sm text-muted-foreground">
                                Upload a JPEG, PNG, or WebP image up to 10 MB.
                                Syncing from GitHub replaces this image.
                            </p>
                            <InputError message={errors.avatar} />
                        </div>
                        {showSaveButton && (
                            <div className="sm:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    aria-busy={processing}
                                    data-test="save-profile-details-button"
                                >
                                    {processing ? <Spinner /> : <Save />}
                                    {processing ? 'Saving profile...' : 'Save profile'}
                                </Button>
                            </div>
                        )}
                    </>
                )}
            </Form>
        </>
    );
}
