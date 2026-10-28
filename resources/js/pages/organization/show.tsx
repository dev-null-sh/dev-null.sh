import { Head } from '@inertiajs/react';
import { Link2, MapPin } from 'lucide-react';
import { show } from '@/actions/App/Http/Controllers/UserController';
import { BioText } from '@/components/bio-text';
import { EmptyFeed } from '@/components/commit-feed';
import { GitHubIcon } from '@/components/github-icon';
import { OrganizationAvatar } from '@/components/organization-avatar';
import { ProfileShareButton } from '@/components/profile-share-button';
import AppLayout from '@/layouts/app-layout';
import type { Organization } from '@/types';

export default function Show({ organization }: { organization: Organization }) {
    return (
        <>
            <Head title={`${organization.name} (@${organization.handle})`} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <p className="min-w-0 truncate font-semibold">
                    {organization.name}
                </p>
                <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                    organization
                </p>
            </div>

            <header className="border-b px-4 pt-6 pb-5 sm:px-6">
                <div className="flex items-start justify-between gap-4">
                    <OrganizationAvatar
                        organization={organization}
                        className="size-20 rounded-lg text-2xl"
                        pixels={160}
                    />

                    <div className="flex shrink-0 gap-1.5 sm:gap-2">
                        <ProfileShareButton
                            handle={organization.handle}
                            href={show(organization).url}
                        />
                    </div>
                </div>

                <h1 className="mt-4 text-2xl font-semibold tracking-tight text-balance">
                    {organization.name}
                </h1>
                <p className="flex items-center gap-1.5 font-mono text-sm text-muted-foreground">
                    @{organization.handle}
                    <span className="ml-1 shrink-0 rounded bg-muted px-1.5 py-0.5 font-sans text-xs">
                        Organization
                    </span>
                </p>

                {organization.bio && (
                    <p className="mt-3 max-w-prose text-[15px] leading-relaxed wrap-break-word whitespace-pre-wrap">
                        <BioText bio={organization.bio} />
                    </p>
                )}

                <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                    {organization.location && (
                        <li className="flex items-center gap-1.5">
                            <MapPin className="size-4" />
                            {organization.location}
                        </li>
                    )}
                    {organization.website && (
                        <li className="flex min-w-0 items-center gap-1.5">
                            <Link2 className="size-4 shrink-0" />
                            <a
                                href={organization.website}
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                className="truncate text-primary hover:underline"
                            >
                                {organization.website.replace(
                                    /^https?:\/\//u,
                                    '',
                                )}
                            </a>
                        </li>
                    )}
                    <li className="flex items-center gap-1.5">
                        <GitHubIcon className="size-4" />
                        <a
                            href={`https://github.com/${organization.handle}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary hover:underline"
                        >
                            github.com/{organization.handle}
                        </a>
                    </li>
                </ul>
            </header>

            <div className="px-6 py-16 text-center sm:px-12">
                <EmptyFeed command={`$ git log --grep=@${organization.handle}`}>
                    Organization pages are new. Soon you'll be able to follow @
                    {organization.handle} and read what the community commits
                    about it, right here.
                </EmptyFeed>
            </div>
        </>
    );
}

Show.layout = AppLayout;
