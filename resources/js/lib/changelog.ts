export type ChangelogPullRequest = {
    number: number;
    type: string | null;
    breaking: boolean;
    title: string;
    url: string;
    sha: string;
    commit_url: string;
    merged_at: string;
    author: { login: string; avatar: string };
};

export type Changelog = {
    pullRequests: ChangelogPullRequest[] | null;
    commitsUrl: string;
};

export type ChangelogDay = {
    date: string;
    pullRequests: ChangelogPullRequest[];
    authors: string[];
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

        if (!day.authors.includes(pullRequest.author.login)) {
            day.authors.push(pullRequest.author.login);
        }
    }

    return days;
}

/**
 * Authors of pull requests may not be on /dev/null, so their lane comes from their GitHub login instead of a profile.
 */
export function authorLane(login: string): number {
    let hash = 0;

    for (const character of login.toLowerCase()) {
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
