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
 * What happened once the stream was over. Stars and replies were counted on October 20.
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
            stars: 427,
            replies: 70,
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
            stars: 142,
            replies: 18,
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
        title: 'Mitchell Hashimoto joins',
        body: 'The co-founder of HashiCorp and creator of Ghostty.',
        kind: 'person',
        person: {
            login: 'mitchellh',
            name: 'Mitchell Hashimoto',
            avatar: 'https://avatars.githubusercontent.com/u/1299?v=4',
        },
    },
    {
        time: 'Oct 19',
        title: '5,000 developers',
        body: 'Four days after the first commit.',
        kind: 'milestone',
    },
    {
        time: 'Oct 19',
        title: 'ThePrimeagen joins',
        body: 'The streamer checks the mic with his first commit.',
        kind: 'person',
        commit: {
            author: {
                login: 'ThePrimeagen',
                name: 'ThePrimeagen',
                avatar: 'https://avatars.githubusercontent.com/u/4458174?v=4',
            },
            id: '01a15693-f3f3-706c-81bf-3271928ee2ab',
            body: 'is this on?',
            stars: 151,
            replies: 42,
        },
    },
    {
        time: 'Oct 20',
        title: 'Wes Bos joins',
        body: 'The teacher behind a generation of JavaScript courses.',
        kind: 'person',
        person: {
            login: 'wesbos',
            name: 'Wes Bos',
            avatar: 'https://avatars.githubusercontent.com/u/176013?v=4',
        },
    },
    {
        time: 'Oct 20',
        title: 'Rasmus Lerdorf joins',
        body: 'The creator of PHP, on a social network built in PHP.',
        kind: 'person',
        person: {
            login: 'rlerdorf',
            name: 'Rasmus Lerdorf',
            avatar: 'https://avatars.githubusercontent.com/u/54641?v=4',
        },
    },
    {
        time: 'Oct 20',
        title: 'Cassidy Williams joins',
        body: 'From GitHub, with memes, dreams, and software.',
        kind: 'person',
        person: {
            login: 'cassidoo',
            name: 'Cassidy Williams',
            avatar: 'https://avatars.githubusercontent.com/u/1454517?v=4',
        },
    },
    {
        time: 'Oct 20',
        title: '#1 Product of the Day',
        body: '“/dev/null - a social network for devs” finishes first on Product Hunt, with 486 points.',
        kind: 'press',
        href: 'https://www.producthunt.com/products/dev-null',
    },
    {
        time: 'Oct 20',
        title: '10,000 developers',
        body: 'Five days after the first commit.',
        kind: 'milestone',
    },
    {
        time: 'Oct 22',
        title: 'Quinn Slack joins',
        body: 'The CEO of Amp.',
        kind: 'person',
        person: {
            login: 'sqs',
            name: 'Quinn Slack',
            avatar: 'https://avatars.githubusercontent.com/u/1976?v=4',
        },
    },
    {
        time: 'Oct 22',
        title: 'Thorsten Ball joins',
        body: 'From the Amp team, and the author of Writing An Interpreter In Go.',
        kind: 'person',
        person: {
            login: 'mrnugget',
            name: 'Thorsten Ball',
            avatar: 'https://avatars.githubusercontent.com/u/1185253?v=4',
        },
    },
];

/**
 * Everyone with a commit on production, most commits first. Bots and agents are left out.
 */
export const storyContributors: StoryContributor[] = [
    { login: 'xHeaven', commits: 217 },
    { login: 'nunomaduro', commits: 189 },
    { login: 'mnapoli', commits: 83 },
    { login: 'brendt', commits: 49 },
    { login: 'paulinevos', commits: 28 },
    { login: 'jubianchi', commits: 24 },
    { login: 'MrPunyapal', commits: 19 },
    { login: 'simonhamp', commits: 19 },
    { login: 'hbenabdallah', commits: 10 },
    { login: 'sadiqk2', commits: 8 },
    { login: 'SanderMuller', commits: 7 },
    { login: 'fernandofreamunde', commits: 6 },
    { login: 'camilleislasse', commits: 6 },
    { login: 'HelgeSverre', commits: 5 },
    { login: 'jjaybrown', commits: 5 },
    { login: 'richardkeep', commits: 5 },
    { login: 'vtlxx', commits: 5 },
    { login: 'robelsust', commits: 4 },
    { login: 'prateekbhujel', commits: 4 },
    { login: 'MaximeGoncalves', commits: 3 },
    { login: 'mathiasonea', commits: 3 },
    { login: 'virtualtear', commits: 2 },
    { login: 'sulimanbenhalim', commits: 2 },
    { login: 'mon4ssi', commits: 2 },
    { login: 'slovenianGooner', commits: 2 },
    { login: 'hbenjacquet', commits: 2 },
    { login: 'ashkesarmaa', commits: 2 },
    { login: 'Akiyo974', commits: 2 },
    { login: 'Chemaclass', commits: 2 },
    { login: 'eznix86', commits: 2 },
    { login: 'BENNIEV-ZA', commits: 2 },
    { login: 'ahosker', commits: 2 },
    { login: 'aaqidmasoodi', commits: 2 },
    { login: 'kachelle', commits: 1 },
    { login: 'lazarospsa', commits: 1 },
    { login: 'Naimur404', commits: 1 },
    { login: 'OpadaAlzaiede', commits: 1 },
    { login: 'ondrejmirtes', commits: 1 },
    { login: 'Heyosseus', commits: 1 },
    { login: 'RobinMalfait', commits: 1 },
    { login: 'rohitsasi720', commits: 1 },
    { login: 'murrant', commits: 1 },
    { login: 'tooinfinity', commits: 1 },
    { login: 'utsavsomaiya', commits: 1 },
    { login: 'vmitchell85', commits: 1 },
    { login: 'anatolesns', commits: 1 },
    { login: 'eramitgupta', commits: 1 },
    { login: 'kadirtikil', commits: 1 },
    { login: 'jigar-dhulla', commits: 1 },
    { login: 'hosmelq', commits: 1 },
    { login: 'hafeez-bw', commits: 1 },
    { login: 'GTS-MEGHNI', commits: 1 },
    { login: 'faissaloux', commits: 1 },
    { login: 'fabpl', commits: 1 },
    { login: 'Taldres', commits: 1 },
    { login: 'danielh-official', commits: 1 },
    { login: 'chrisgocode', commits: 1 },
    { login: 'chiragparekh', commits: 1 },
    { login: 'AmanuelLegese', commits: 1 },
    { login: 'albertoarena', commits: 1 },
    { login: 'alaminfirdows', commits: 1 },
];
