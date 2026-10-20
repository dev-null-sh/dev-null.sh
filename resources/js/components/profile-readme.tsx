import { EmptyFeed } from '@/components/commit-feed';

/**
 * The README of the developer's GitHub profile repository. The server only sends it once sanitized, with plain
 * formatting, links and images through GitHub's image proxy.
 */
export function ProfileReadme({
    readme,
    username,
    isCurrentUser,
}: {
    readme: string | null;
    username: string;
    isCurrentUser: boolean;
}) {
    if (readme === null) {
        return (
            <div className="px-6 py-16 text-center sm:px-12">
                <EmptyFeed command="fatal: README.md: No such file or directory">
                    {isCurrentUser ? (
                        <>
                            Add a README.md to a GitHub repository named{' '}
                            <span className="font-mono">{username}</span>, and
                            it shows up here.{' '}
                            <a
                                href="https://docs.github.com/en/account-and-profile/setting-up-and-managing-your-github-profile/customizing-your-profile/managing-your-profile-readme"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary hover:underline"
                            >
                                How to
                            </a>
                        </>
                    ) : (
                        `@${username} doesn't have a profile README on GitHub.`
                    )}
                </EmptyFeed>
            </div>
        );
    }

    return (
        <div className="px-4 py-5 sm:px-6">
            <div
                className="readme"
                data-test="profile-readme"
                dangerouslySetInnerHTML={{ __html: readme }}
            />
            <a
                href={`https://github.com/${username}/${username}`}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-6 block font-mono text-xs text-muted-foreground hover:text-foreground"
            >
                {username}/README.md on GitHub
            </a>
        </div>
    );
}
