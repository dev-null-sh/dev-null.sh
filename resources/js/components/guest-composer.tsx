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

            <div className="min-w-0 flex-1 py-4 pr-4">
                <h2
                    id="guest-composer"
                    className="text-[17px] leading-snug font-semibold tracking-tight text-balance"
                >
                    The social network for developers.
                </h2>
                <p className="mt-1 text-[15px] text-pretty text-muted-foreground">
                    Everyone below signed in with GitHub and committed plain
                    text. Your turn.
                </p>

                <a
                    href={login().url}
                    aria-label="Sign in with GitHub to commit"
                    className="mt-3 flex min-h-10 items-center rounded-lg border border-dashed px-3 py-1.5 text-[17px] text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                >
                    What did you ship?
                    <span
                        aria-hidden
                        className="ml-0.5 h-5 w-0.5 bg-primary motion-safe:animate-pulse"
                    />
                </a>

                <div className="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <p className="text-sm text-muted-foreground">
                        Your profile comes from GitHub. Nothing to fill in.
                    </p>
                    <Button size="sm" asChild>
                        <a href={login().url} data-test="login-button">
                            <GitHubIcon className="size-4" />
                            Sign in with GitHub
                        </a>
                    </Button>
                </div>
            </div>
        </section>
    );
}
