export type ChangelogPullRequest = {
    number: number;
    type: string | null;
    breaking: boolean;
    title: string;
    url: string;
    sha: string;
    commit_url: string;
    merged_at: string;
    author: ChangelogAuthor;
};

/**
 * Someone who merged a pull request. The username, mention and lane are theirs on /dev/null, or null when they aren't
 * on it.
 */
export type ChangelogAuthor = {
    login: string;
    avatar: string;
    username: string | null;
    mention: string | null;
    lane: number | string | null;
};

export type Changelog = {
    pullRequests: ChangelogPullRequest[] | null;
    olderBefore: string | null;
    commitsUrl: string;
};

export type ChangelogDay = {
    date: string;
    pullRequests: ChangelogPullRequest[];
    authors: ChangelogAuthor[];
};

const laneCount = 6;

/**
 * Groups the merges, already newest first, by the calendar day they happened on in the reader's time zone.
 */
export function changelogDays(
    pullRequests: ChangelogPullRequest[],
    timeZone: string,
): ChangelogDay[] {
    const days: ChangelogDay[] = [];

    for (const pullRequest of pullRequests) {
        const date = new Date(pullRequest.merged_at).toLocaleDateString(
            'en-CA',
            { timeZone },
        );
        let day = days.at(-1);

        if (day?.date !== date) {
            day = { date, pullRequests: [], authors: [] };
            days.push(day);
        }

        day.pullRequests.push(pullRequest);

        if (
            !day.authors.some(
                (author) => author.login === pullRequest.author.login,
            )
        ) {
            day.authors.push(pullRequest.author);
        }
    }

    return days;
}

/**
 * The lane an author chose on /dev/null, or, for someone who isn't on it, one that always follows from their login.
 */
export function authorLane(
    author: Pick<ChangelogAuthor, 'login' | 'lane'>,
): number | string {
    if (author.lane !== null) {
        return author.lane;
    }

    let hash = 0;

    for (const character of author.login.toLowerCase()) {
        hash = (hash * 31 + character.charCodeAt(0)) >>> 0;
    }

    return hash % laneCount;
}

/**
 * "Today", "Yesterday" or the weekday, with the date underneath it, for a day from changelogDays().
 */
export function changelogDayHeading(
    date: string,
    timeZone: string,
    now: Date = new Date(),
): { title: string; date: string } {
    const day = new Date(`${date}T12:00:00Z`);
    const today = new Date(
        `${now.toLocaleDateString('en-CA', { timeZone })}T12:00:00Z`,
    );
    const daysAgo = Math.round((today.getTime() - day.getTime()) / 86_400_000);

    return {
        title:
            daysAgo === 0
                ? 'Today'
                : daysAgo === 1
                  ? 'Yesterday'
                  : day.toLocaleDateString('en-US', {
                        weekday: 'long',
                        timeZone: 'UTC',
                    }),
        date: day.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year:
                day.getUTCFullYear() === today.getUTCFullYear()
                    ? undefined
                    : 'numeric',
            timeZone: 'UTC',
        }),
    };
}
