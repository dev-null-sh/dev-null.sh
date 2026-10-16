import { http, usePage } from '@inertiajs/react';
import { Flag } from 'lucide-react';
import type { CSSProperties, KeyboardEvent, MouseEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import MinesweeperGameController from '@/actions/App/Http/Controllers/MinesweeperGameController';
import { levelColor } from '@/components/activity-graph';
import { Button } from '@/components/ui/button';
import {
    cellCount,
    chord,
    columns,
    formatDuration,
    mineCount,
    move,
} from '@/lib/minesweeper';
import { cn, laneColor } from '@/lib/utils';
import type { Commit, MinesweeperGame, MinesweeperScores } from '@/types';

const weekdayLabels = ['', 'Mon', '', 'Wed', '', 'Fri', ''];

const emptyCells: null[] = Array.from({ length: cellCount }, () => null);

type Route = { url: string; method: 'get' | 'post' | 'put' };

async function send(route: Route, data?: object): Promise<MinesweeperScores> {
    const response = await http.getClient().request({
        method: route.method,
        url: route.url,
        data,
        headers: { Accept: 'application/json' },
    });

    return JSON.parse(response.data) as MinesweeperScores;
}

/**
 * Hidden cells are the graph's empty days. Revealed ones are dug out of it, and heat up with the mines around them,
 * so a cleared board reads like a busy year.
 */
function cellBackground(count: number | null): string {
    if (count === null) {
        return levelColor(0);
    }

    return count === 0 ? 'transparent' : levelColor(Math.min(count, 3) + 1);
}

const legend = [null, 0, 1, 2, 3];

export function Minesweeper({ commit }: { commit: Commit }) {
    const { auth } = usePage().props;
    const [scores, setScores] = useState<MinesweeperScores | null>(null);
    const [isStartingOver, setIsStartingOver] = useState(false);
    const [flags, setFlags] = useState<ReadonlySet<number>>(new Set());
    const [isFlagging, setIsFlagging] = useState(false);
    const [isBusy, setIsBusy] = useState(false);
    const [focusedCell, setFocusedCell] = useState(0);
    const [now, setNow] = useState(() => Date.now());
    const cellRefs = useRef<(HTMLButtonElement | null)[]>([]);

    const game: MinesweeperGame | null = isStartingOver
        ? null
        : (scores?.game ?? null);
    const cells = game?.cells ?? emptyCells;
    const isPlaying = game?.status === 'playing';
    const isFinished = game !== null && !isPlaying;
    const mines = new Set(game?.mines ?? []);

    const isSignedIn = auth.user !== null;
    const commitId = commit.id;

    useEffect(() => {
        if (!isSignedIn) {
            return;
        }

        let isCancelled = false;

        send(MinesweeperGameController.index(commitId))
            .then((loaded) => {
                if (!isCancelled) {
                    setScores(loaded);
                }
            })
            .catch(() => undefined);

        return () => {
            isCancelled = true;
        };
    }, [isSignedIn, commitId]);

    useEffect(() => {
        if (!isPlaying) {
            return;
        }

        const interval = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(interval);
    }, [isPlaying]);

    const play = async (route: Route, data: object) => {
        setIsBusy(true);

        try {
            const played = await send(route, data);

            setScores(played);
            setIsStartingOver(false);
        } catch {
            toast.error('That move went to /dev/null. Try again.');
        } finally {
            setIsBusy(false);
        }
    };

    const toggleFlag = (cell: number) => {
        if (!isPlaying || cells[cell] !== null) {
            return;
        }

        setFlags((current) => {
            const next = new Set(current);

            if (!next.delete(cell)) {
                next.add(cell);
            }

            return next;
        });
    };

    const reveal = (cell: number) => {
        if (isBusy || isFinished || flags.has(cell)) {
            return;
        }

        if (game === null) {
            setFlags(new Set());
            void play(MinesweeperGameController.store(commit), { cell });

            return;
        }

        const targets =
            cells[cell] === null ? [cell] : chord(cell, cells, flags);

        if (targets.length > 0) {
            void play(
                MinesweeperGameController.update({
                    commit,
                    minesweeperGame: game.id,
                }),
                { cells: targets },
            );
        }
    };

    const press = (cell: number) => {
        setFocusedCell(cell);

        if (isFlagging && cells[cell] === null) {
            toggleFlag(cell);
        } else {
            reveal(cell);
        }
    };

    const onContextMenu = (cell: number, event: MouseEvent) => {
        event.preventDefault();
        toggleFlag(cell);
    };

    // The board keeps its keys to itself, so the log's shortcuts never fire mid-game...
    const onKeyDown = (cell: number, event: KeyboardEvent) => {
        const next = move(cell, event.key);

        if (next !== null) {
            setFocusedCell(next);
            cellRefs.current[next]?.focus();
        } else if (event.key === 'Enter' || event.key === ' ') {
            press(cell);
        } else if (event.key === 'f') {
            toggleFlag(cell);
        } else {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
    };

    const startOver = () => {
        setIsStartingOver(true);
        setFlags(new Set());
    };

    const elapsed =
        game === null
            ? 0
            : (game.duration_ms ?? now - Date.parse(game.created_at));
    const minesLeft = isPlaying
        ? mineCount - flags.size
        : game?.status === 'won'
          ? 0
          : mineCount;
    const isPersonalBest =
        game?.status === 'won' && game.duration_ms === scores?.personal_best;

    const status = (() => {
        if (game?.status === 'won') {
            return isPersonalBest
                ? `Cleared in ${formatDuration(elapsed, true)}. A new personal best.`
                : `Cleared in ${formatDuration(elapsed, true)}.`;
        }

        if (game?.status === 'lost') {
            return `Boom. ${formatDuration(elapsed, true)} in, that one went to /dev/null.`;
        }

        return 'Clear the graph. Right-click or F to flag.';
    })();

    const scoreline = [
        `Best ${scores?.best ? `${formatDuration(scores.best.duration_ms, true)} @${scores.best.user.username}` : '—'}`,
        `You ${scores?.personal_best != null ? formatDuration(scores.personal_best, true) : '—'}`,
        `${scores?.played ?? 0} played`,
    ].join(' · ');

    const label = (cell: number): string => {
        const row = Math.floor(cell / columns) + 1;
        const column = (cell % columns) + 1;
        const count = cells[cell];
        const state =
            cell === game?.exploded_cell
                ? 'exploded mine'
                : mines.has(cell)
                  ? 'mine'
                  : count !== null
                    ? count === 0
                        ? 'clear'
                        : `${count} ${count === 1 ? 'mine' : 'mines'} nearby`
                    : flags.has(cell)
                      ? 'flagged'
                      : 'hidden';

        return `Row ${row}, column ${column}, ${state}`;
    };

    return (
        <section
            aria-label="Minesweeper"
            className="mt-3 rounded-lg border px-3 py-3 sm:px-4"
            style={{ '--lane': laneColor(commit.user.lane) } as CSSProperties}
            data-test="minesweeper"
        >
            <div className="mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <h3 className="text-sm font-semibold">Minesweeper</h3>
                <div className="flex items-center gap-3 font-mono text-[13px] text-muted-foreground">
                    <span
                        className="inline-block min-w-[6ch] text-right tabular-nums"
                        title="Mines left"
                        data-test="minesweeper-mines-left"
                    >
                        {minesLeft} {minesLeft === 1 ? 'mine' : 'mines'}
                    </span>
                    <span
                        className="inline-block min-w-[6ch] text-right text-foreground tabular-nums"
                        title="Time"
                        data-test="minesweeper-time"
                    >
                        {formatDuration(elapsed, isFinished)}
                    </span>
                    <button
                        type="button"
                        onClick={() => setIsFlagging((current) => !current)}
                        aria-pressed={isFlagging}
                        aria-label="Flag mode"
                        title="Flag mode: tap a square to flag it"
                        className={cn(
                            'rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            {
                                'bg-primary-soft text-primary hover:text-primary':
                                    isFlagging,
                            },
                        )}
                        data-test="minesweeper-flag-mode"
                    >
                        <Flag aria-hidden className="size-4" />
                    </button>
                </div>
            </div>

            <div className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-2">
                <div
                    aria-hidden
                    className="grid grid-rows-7 gap-[3px] text-xs text-muted-foreground"
                >
                    {weekdayLabels.map((label, index) => (
                        <span
                            key={index}
                            className="flex items-center leading-none"
                        >
                            {label}
                        </span>
                    ))}
                </div>

                <div
                    role="group"
                    aria-label="Board"
                    aria-busy={isBusy}
                    className="grid gap-[3px]"
                    style={{
                        gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`,
                    }}
                >
                    {cells.map((count, cell) => {
                        const isMine =
                            mines.has(cell) && game?.status === 'lost';
                        const isFlagged =
                            count === null &&
                            (flags.has(cell) ||
                                (mines.has(cell) && game?.status === 'won'));

                        return (
                            <button
                                key={cell}
                                ref={(node) => {
                                    cellRefs.current[cell] = node;
                                }}
                                type="button"
                                tabIndex={cell === focusedCell ? 0 : -1}
                                onClick={() => press(cell)}
                                onContextMenu={(event) =>
                                    onContextMenu(cell, event)
                                }
                                onKeyDown={(event) => onKeyDown(cell, event)}
                                aria-label={label(cell)}
                                className={cn(
                                    'flex aspect-square items-center justify-center rounded-[2px] font-mono text-[10px] leading-none font-medium tabular-nums transition-shadow outline-none select-none focus-visible:shadow-[0_0_0_2px_var(--ring)] sm:text-xs',
                                    {
                                        'cursor-pointer hover:shadow-[0_0_0_1.5px_var(--foreground)]':
                                            count === null && !isFinished,
                                        'bg-destructive': isMine,
                                        'shadow-[0_0_0_2px_var(--foreground)]':
                                            cell === game?.exploded_cell,
                                        'shadow-[inset_0_0_0_1px_var(--muted)]':
                                            count === 0,
                                        'text-foreground': count === 1,
                                        'text-card':
                                            count !== null && count > 1,
                                    },
                                )}
                                style={
                                    isMine
                                        ? undefined
                                        : { background: cellBackground(count) }
                                }
                                data-test="minesweeper-cell"
                                data-state={
                                    isMine
                                        ? 'mine'
                                        : count !== null
                                          ? 'revealed'
                                          : isFlagged
                                            ? 'flagged'
                                            : 'hidden'
                                }
                            >
                                {isMine ? (
                                    <span
                                        aria-hidden
                                        className="size-1.5 rounded-full bg-card"
                                    />
                                ) : isFlagged ? (
                                    <Flag
                                        aria-hidden
                                        className="size-2.5 fill-current text-(--lane) sm:size-3"
                                    />
                                ) : count !== null && count > 0 ? (
                                    count
                                ) : null}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div
                aria-hidden
                className="mt-2 flex items-center justify-end gap-1 text-xs text-muted-foreground"
            >
                <span className="mr-1">Hidden</span>
                {legend.map((count) => (
                    <span
                        key={String(count)}
                        className={cn('size-2.5 rounded-[2px]', {
                            'shadow-[inset_0_0_0_1px_var(--muted)]':
                                count === 0,
                        })}
                        style={{ background: cellBackground(count) }}
                    />
                ))}
                <span className="ml-1">3+ mines nearby</span>
            </div>

            <div className="mt-3 flex min-h-8 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t pt-3">
                <div className="min-w-0 flex-1 basis-full text-sm sm:basis-auto">
                    <p
                        role="status"
                        className="truncate"
                        title={status}
                        data-test="minesweeper-status"
                    >
                        {status}
                    </p>
                    <p
                        className="truncate font-mono text-xs text-muted-foreground tabular-nums"
                        title={scoreline}
                        data-test="minesweeper-scores"
                    >
                        {scoreline}
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    className="ml-auto min-w-24"
                    onClick={startOver}
                    disabled={game === null}
                    data-test="minesweeper-new-game"
                >
                    New game
                </Button>
            </div>
        </section>
    );
}
