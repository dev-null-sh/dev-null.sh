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

export const repositoryUrl = 'https://github.com/nunomaduro/dev-null.sh';

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

/**
 * Everyone with a commit on production, most commits first. Bots and agents are left out.
 */
export const storyContributors: StoryContributor[] = [
    { login: 'xHeaven', commits: 199 },
    { login: 'nunomaduro', commits: 176 },
    { login: 'mnapoli', commits: 61 },
    { login: 'brendt', commits: 42 },
    { login: 'simonhamp', commits: 18 },
    { login: 'paulinevos', commits: 16 },
    { login: 'jubianchi', commits: 16 },
    { login: 'MrPunyapal', commits: 15 },
    { login: 'sadiqk2', commits: 8 },
    { login: 'camilleislasse', commits: 6 },
    { login: 'hbenabdallah', commits: 5 },
    { login: 'vtlxx', commits: 5 },
    { login: 'robelsust', commits: 4 },
    { login: 'HelgeSverre', commits: 4 },
    { login: 'SanderMuller', commits: 3 },
    { login: 'MaximeGoncalves', commits: 3 },
    { login: 'mathiasonea', commits: 3 },
    { login: 'fernandofreamunde', commits: 3 },
    { login: 'mon4ssi', commits: 2 },
    { login: 'hbenjacquet', commits: 2 },
    { login: 'Akiyo974', commits: 2 },
    { login: 'Chemaclass', commits: 2 },
    { login: 'eznix86', commits: 2 },
    { login: 'BENNIEV-ZA', commits: 2 },
    { login: 'ahosker', commits: 2 },
    { login: 'eramitgupta', commits: 1 },
    { login: 'utsavsomaiya', commits: 1 },
    { login: 'tooinfinity', commits: 1 },
    { login: 'murrant', commits: 1 },
    { login: 'RobinMalfait', commits: 1 },
    { login: 'prateekbhujel', commits: 1 },
    { login: 'ondrejmirtes', commits: 1 },
    { login: 'lazarospsa', commits: 1 },
    { login: 'kachelle', commits: 1 },
    { login: 'kadirtikil', commits: 1 },
    { login: 'jjaybrown', commits: 1 },
    { login: 'GTS-MEGHNI', commits: 1 },
    { login: 'faissaloux', commits: 1 },
    { login: 'fabpl', commits: 1 },
    { login: 'danielh-official', commits: 1 },
    { login: 'chrisgocode', commits: 1 },
    { login: 'AmanuelLegese', commits: 1 },
    { login: 'alaminfirdows', commits: 1 },
    { login: 'aaqidmasoodi', commits: 1 },
];
