import { usePoll } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { GitHubIcon } from '@/components/github-icon';
import { useNow } from '@/hooks/use-now';
import { cn, formatRelativeTime } from '@/lib/utils';

export type RepoPetData = {
    repository: string;
    workflow: string;
    mood: 'happy' | 'busy' | 'grumpy' | 'sleeping';
    message: string;
    url: string;
    checked_at: string | null;
};

export type RepoPetConfiguration = {
    repository: string;
    repository_id: number;
    workflow_id: number;
    workflow: string;
};

const moodLabels = {
    happy: 'Happy',
    busy: 'Busy',
    grumpy: 'Grumpy',
    sleeping: 'Sleeping',
};

export function RepoPetCreature({
    mood,
    className,
}: {
    mood: RepoPetData['mood'];
    className?: string;
}) {
    return (
        <svg
            viewBox="0 0 144 120"
            aria-hidden
            className={cn('repo-pet-creature w-28 shrink-0', className)}
            data-mood={mood}
        >
            <ellipse
                cx="72"
                cy="108"
                rx="32"
                ry="4"
                fill="currentColor"
                opacity="0.08"
            />
            <g className="repo-pet-body">
                <g
                    stroke="var(--pet-ink)"
                    strokeWidth="4"
                    strokeLinecap="round"
                >
                    <path d="M53 91v12h-9" fill="none" />
                    <path
                        d="M91 91v12h9"
                        fill="none"
                        className="repo-pet-foot"
                    />
                    <path d="M43 62 32 68l-4-4M101 62l11 6 4-4" fill="none" />
                    <path d="M72 30V19l9-5" fill="none" />
                </g>
                <circle
                    cx="83"
                    cy="13"
                    r="5"
                    fill="var(--pet-shell)"
                    stroke="var(--pet-ink)"
                    strokeWidth="3"
                />
                <rect
                    x="39"
                    y="28"
                    width="66"
                    height="67"
                    rx="20"
                    fill="var(--pet-shell)"
                    stroke="var(--pet-ink)"
                    strokeWidth="3"
                />
                <path
                    d="M50 84h44"
                    fill="none"
                    stroke="var(--pet-ink)"
                    strokeWidth="3"
                    strokeLinecap="round"
                    opacity="0.2"
                />
                <circle
                    cx="49"
                    cy="69"
                    r="4"
                    fill="var(--pet-ink)"
                    opacity="0.1"
                />
                <circle
                    cx="95"
                    cy="69"
                    r="4"
                    fill="var(--pet-ink)"
                    opacity="0.1"
                />

                <g
                    fill="none"
                    stroke="var(--pet-ink)"
                    strokeWidth="3.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                >
                    {mood === 'happy' && (
                        <>
                            <path d="M52 56q5-8 10 0m20 0q5-8 10 0" />
                            <path d="M64 69q8 12 16 0" />
                        </>
                    )}
                    {mood === 'busy' && (
                        <>
                            <path d="M56 51v8m32-8v8" />
                            <circle cx="72" cy="73" r="5" />
                        </>
                    )}
                    {mood === 'grumpy' && (
                        <>
                            <path d="m50 49 13 5m18 0 13-5M57 58v3m30-3v3" />
                            <path d="M64 75q8-8 16 0" />
                        </>
                    )}
                    {mood === 'sleeping' && (
                        <>
                            <path d="m51 57 5 3 6-3m20 0 5 3 6-3" />
                            <path d="M69 74h6" />
                        </>
                    )}
                </g>
                <path
                    d="M48 38h6m4 0h2"
                    stroke="var(--pet-ink)"
                    strokeWidth="2"
                    strokeLinecap="round"
                    opacity="0.35"
                />
            </g>

            {mood === 'happy' && (
                <path
                    d="M22 27v10m-5-5h10m89 39v8m-4-4h8"
                    stroke="var(--pet-ink)"
                    strokeWidth="2"
                    strokeLinecap="round"
                    className="repo-pet-signal"
                />
            )}
            {mood === 'busy' && (
                <g fill="var(--pet-ink)" className="repo-pet-signal">
                    <circle cx="113" cy="28" r="2.5" />
                    <circle cx="122" cy="28" r="2.5" />
                    <circle cx="131" cy="28" r="2.5" />
                </g>
            )}
            {mood === 'grumpy' && (
                <path
                    d="m119 34-6 8h7l-6 8"
                    fill="none"
                    stroke="var(--pet-ink)"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            )}
            {mood === 'sleeping' && (
                <text
                    x="112"
                    y="38"
                    fill="var(--pet-ink)"
                    fontFamily="monospace"
                    fontSize="17"
                    className="repo-pet-signal"
                >
                    z
                </text>
            )}
        </svg>
    );
}

export function RepoPet({
    pet,
    className,
}: {
    pet: RepoPetData;
    className?: string;
}) {
    const now = useNow();
    usePoll(30_000, { only: ['repoPet'] });

    return (
        <section
            aria-label="Repo pet"
            className={cn(
                'flex items-center gap-2 rounded-lg border bg-muted/20 p-3 sm:gap-4',
                className,
            )}
            data-test="repo-pet"
            data-mood={pet.mood}
        >
            <RepoPetCreature mood={pet.mood} className="w-24 sm:w-28" />
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h2 className="text-sm font-semibold">Repo pet</h2>
                    <span className="rounded-full bg-muted px-2 py-0.5 font-mono text-[10px] text-muted-foreground">
                        {moodLabels[pet.mood]}
                    </span>
                </div>
                <p className="mt-1 text-sm" role="status">
                    <span className="sr-only">{moodLabels[pet.mood]}. </span>
                    {pet.message}
                </p>
                <a
                    href={pet.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={`Open ${pet.workflow} for ${pet.repository} on GitHub`}
                    className="mt-2 inline-flex max-w-full items-center gap-1.5 font-mono text-xs text-muted-foreground hover:text-foreground hover:underline"
                >
                    <GitHubIcon className="size-3 shrink-0" />
                    <span className="truncate">{pet.repository}</span>
                    <ArrowUpRight className="size-3 shrink-0" />
                </a>
                <p
                    className="truncate text-xs text-muted-foreground"
                    title={pet.workflow}
                >
                    {pet.workflow}
                </p>
                {pet.checked_at && (
                    <p className="mt-1 text-[11px] text-muted-foreground">
                        Checked{' '}
                        <time
                            dateTime={pet.checked_at}
                            title={new Date(pet.checked_at).toLocaleString()}
                        >
                            {formatRelativeTime(pet.checked_at, now)}
                        </time>
                    </p>
                )}
            </div>
        </section>
    );
}
