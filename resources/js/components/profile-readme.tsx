import { EmptyFeed } from '@/components/commit-feed';

/**
 * The README of the developer's GitHub profile repository, which is named after their GitHub login rather than their
 * handle, as the two can differ after a rename. The server only sends it once sanitized, with plain
 * formatting, links and images from GitHub's own hosts. False when GitHub didn't answer, which is not the same as
 * having no README.
 */
export function ProfileReadme({
    readme,
    handle,
    repository,
    isCurrentUser,
}: {
    readme: string | false | null;
    handle: string;
    repository: string;
    isCurrentUser: boolean;
}) {
    if (readme === false) {
        return (
            <div className="px-6 py-16 text-center sm:px-12">
                <EmptyFeed command="fatal: unable to access 'https://github.com/'">
                    GitHub didn't answer. Try again in a few minutes.
                </EmptyFeed>
            </div>
        );
    }

    if (readme === null) {
        return (
            <div className="px-6 py-16 text-center sm:px-12">
                <EmptyFeed command="fatal: README.md: No such file or directory">
                    {isCurrentUser ? (
                        <>
                            Add a README.md to a GitHub repository named{' '}
                            <span className="font-mono">{repository}</span>, and
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
                        `@${handle} doesn't have a profile README on GitHub.`
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
                href={`https://github.com/${repository}/${repository}`}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-6 block font-mono text-xs text-muted-foreground hover:text-foreground"
            >
                {repository}/README.md on GitHub
            </a>
        </div>
    );
}
