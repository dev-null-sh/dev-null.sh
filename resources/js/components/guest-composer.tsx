import { useLang } from '@erag/lang-sync-inertia/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { GitHubIcon } from '@/components/github-icon';
import { GraphRail } from '@/components/graph-rail';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';

/**
 * Guests read the same log everyone does, so the composer on top of it is where they sign in. Signing in leaves for
 * GitHub, which an Inertia visit cannot follow, so these are plain links.
 */
export function GuestComposer() {
    const { __ } = useLang();

    return (
        <section
            aria-labelledby="guest-composer"
            className="flex border-b"
            data-test="guest-composer"
        >
            <GraphRail
                lane="var(--primary)"
                variant="staged"
                isFilled={false}
                node={
                    <span className="flex rounded-full bg-card">
                        <span className="flex rounded-full border-2 border-dashed border-(--lane) p-0.5">
                            <span className="flex size-10 items-center justify-center rounded-full bg-primary-soft">
                                <AppLogoIcon className="size-5 fill-current" />
                            </span>
                        </span>
                    </span>
                }
            />

            <div className="min-w-0 flex-1 pt-4 pr-4 pb-4">
                <h2
                    id="guest-composer"
                    className="text-[17px] leading-snug font-semibold tracking-tight text-balance"
                >
                    {__('composer.guest.title')}
                </h2>
                <p className="mt-1 text-[15px] text-pretty text-muted-foreground">
                    {__('composer.guest.description')}
                </p>

                <a
                    href={login().url}
                    aria-label={__('composer.guest.sign_in_to_commit')}
                    className="mt-3 flex min-h-10 items-center rounded-lg border border-dashed px-3 py-1.5 text-[17px] text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                >
                    {__('composer.placeholder')}
                    <span
                        aria-hidden
                        className="ml-0.5 h-5 w-0.5 bg-primary motion-safe:animate-pulse"
                    />
                </a>

                <div className="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <p className="text-sm text-muted-foreground">
                        {__('composer.guest.profile')}
                    </p>
                    <Button size="sm" asChild>
                        <a href={login().url} data-test="login-button">
                            <GitHubIcon className="size-4" />
                            {__('composer.guest.sign_in')}
                        </a>
                    </Button>
                </div>
            </div>
        </section>
    );
}
