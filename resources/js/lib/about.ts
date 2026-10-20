import type { Lane } from '@/types';

export type StoryEventKind = 'person' | 'milestone' | 'press';

export type StoryAuthor = StoryPerson & { avatar: string };

export type StoryCommit = {
    author: StoryAuthor;
    id: string;
    body: string;
    stars: number;
    replies: number;
};

export type StoryEvent = {
    time: string;
    title: string;
    body?: string;
    kind: StoryEventKind;
    href?: string;
    person?: StoryAuthor;
    commit?: StoryCommit;
};

export type StoryStream = {
    id: string;
    title: string;
    date: string;
    videoId: string;
    highlights: string[];
};

export type StoryPerson = {
    login: string;
    name: string;
};

export type StoryContributor = {
    login: string;
    commits: number;
};

export const storyBefore: StoryEvent[] = [
    {
        time: 'Sep 15',
        title: 'The idea',
        body: '“A full social network. Built in PHP. In 48 hours. Live from the JetBrains office.” Nuno Maduro announces two days of streaming with Brent Roose and Matthieu Napoli.',
        kind: 'milestone',
        href: 'https://x.com/enunomaduro/status/2099805253692518682',
    },
];

/**
 * The people who built the first version on stream.
 */
export const storyFounders: StoryPerson[] = [
    { login: 'nunomaduro', name: 'Nuno Maduro' },
    { login: 'brendt', name: 'Brent Roose' },
    { login: 'mnapoli', name: 'Matthieu Napoli' },
    { login: 'paulinevos', name: 'Pauline Vos' },
];

export const storyStreams: StoryStream[] = [
    {
        id: 'day-one',
        title: 'Day one',
        date: 'Thursday, October 15, 2026',
        videoId: '6zCNQa3hNm0',
        highlights: [
            'An empty repository in the morning, live in production by lunch.',
            'Sign in with GitHub, commits, the log, follows, and replies.',
            'Moderation, stars, link previews, and commits from the terminal.',
            '155 commits from 20 contributors, and 39 open pull requests by the end of the stream.',
        ],
    },
    {
        id: 'day-two',
        title: 'Day two',
        date: 'Friday, October 16, 2026',
        videoId: 'nfmVdXZfzUE',
        highlights: [
            'Quotes, polls, long commits, and the public read API.',
            'AI moderation, so automod approves commits.',
            '@world, achievements, and pastéis mode.',
            '221 commits, and a social network 48 hours after the first one.',
        ],
    },
];

export const storyAfterLane: Lane = 2;

/**
 * What happened once the stream was over. Stars and replies were counted on October 18.
 */
export const storyAfter: StoryEvent[] = [
    {
        time: 'Oct 16',
        title: 'Taylor Otwell joins',
        body: 'The creator of Laravel shows up mid-stream.',
        kind: 'person',
        commit: {
            author: {
                login: 'taylorotwell',
                name: 'Taylor Otwell',
                avatar: 'https://avatars.githubusercontent.com/u/463230?v=4',
            },
            id: '01a144ea-a3c5-724f-82e0-92f9b4b973c0',
            body: 'What’s up everyone! Laravel forever. 🔥',
            stars: 392,
            replies: 78,
        },
    },
    {
        time: 'Oct 16',
        title: 'Viral on Hacker News',
        body: '“/dev/null: social network for developers” ends up with 176 points and 167 comments.',
        kind: 'press',
        href: 'https://news.ycombinator.com/item?id=50121004',
    },
    {
        time: 'Oct 16',
        title: '1,000 developers',
        body: '“ok, we getting viral, help.”',
        kind: 'milestone',
    },
    {
        time: 'Oct 17',
        title: '2,000 developers',
        body: 'Two days after the first commit.',
        kind: 'milestone',
    },
    {
        time: 'Oct 17',
        title: 'Guillermo Rauch joins',
        body: 'The CEO of Vercel, a minute after signing in.',
        kind: 'person',
        commit: {
            author: {
                login: 'rauchg',
                name: 'Guillermo Rauch',
                avatar: 'https://avatars.githubusercontent.com/u/13041?v=4',
            },
            id: '01a14c0f-65e2-706b-9a16-20650373748c',
            body: 'This is really well done',
            stars: 97,
            replies: 14,
        },
    },
    {
        time: 'Oct 18',
        title: '3,000 developers',
        body: 'And more than 300 pull requests sent to the repository.',
        kind: 'milestone',
    },
    {
        time: 'Oct 18',
        title: 'Márk Magyar joins the core team',
        body: 'More commits than anyone, from polls and the public API to Remotes.',
        kind: 'person',
        person: {
            login: 'xHeaven',
            name: 'Márk Magyar',
            avatar: 'https://avatars.githubusercontent.com/u/14284867?v=4',
        },
    },
    {
        time: 'Oct 18',
        title: 'Lauren Tan joins',
        body: 'Software engineer at xAI, and on the React Compiler core team.',
        kind: 'person',
        commit: {
            author: {
                login: 'poteto',
                name: 'lauren',
                avatar: 'https://avatars.githubusercontent.com/u/1390709?v=4',
            },
            id: '01a15144-0c2f-7093-bace-9d6026e5f244',
            body: '👀 hi',
            stars: 11,
            replies: 5,
        },
    },
];
