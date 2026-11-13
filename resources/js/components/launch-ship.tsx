import { Users } from 'lucide-react';
import type { CSSProperties } from 'react';
import { useId } from 'react';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';

export type LaunchPassenger = {
    id: string;
    handle: string;
    name: string;
    avatar_url: string | null;
};

export function LaunchShip({
    count,
    passengers,
    captain,
}: {
    count: number;
    passengers: LaunchPassenger[];
    captain?: Pick<LaunchPassenger, 'name' | 'handle' | 'avatar_url'>;
}) {
    const waveId = useId();
    const shown = passengers.slice(0, 16).toReversed();
    const excess = Math.max(0, count - shown.length);
    const decks = Array.from(
        { length: Math.ceil(shown.length / 6) },
        (_, index) => index,
    );

    return (
        <div
            className={cn('launch-sea', count >= 8 && 'launch-overloaded')}
            style={
                {
                    '--launch-sink': `${Math.min(count, 32) * 0.45}px`,
                } as CSSProperties
            }
            data-test="launch-ship"
            data-passengers={count}
        >
            <div className="launch-vessel">
                <div className="launch-bob">
                    <svg
                        viewBox="0 0 240 100"
                        fill="none"
                        className="launch-hull"
                        aria-hidden="true"
                    >
                        <path
                            d="M178 61V24h31v37"
                            fill="var(--background)"
                            stroke="currentColor"
                            strokeWidth="2"
                        />
                        <path
                            d="M185 15h12v10h-12z"
                            fill="var(--muted-foreground)"
                        />
                        <rect
                            x="185"
                            y="33"
                            width="16"
                            height="12"
                            rx="2"
                            fill="var(--launch-water)"
                            stroke="currentColor"
                            strokeWidth="1.5"
                        />
                        <path
                            d="M8 61h224l-24 29H39C26 90 14 75 8 61Z"
                            fill="var(--foreground)"
                        />
                        <path d="M13 68h212" stroke="#eea65e" strokeWidth="4" />
                        <text
                            x="62"
                            y="82"
                            fill="var(--background)"
                            fontFamily="var(--font-mono)"
                            fontSize="9"
                            letterSpacing="1.5"
                        >
                            shipped it
                        </text>
                        <circle
                            cx="180"
                            cy="77"
                            r="5"
                            stroke="var(--background)"
                            strokeWidth="2"
                        />
                        <path
                            d="M209 22V4m0 0 24 7-24 7"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinejoin="round"
                            fill="#eea65e"
                        />
                    </svg>
                    {captain && (
                        <span
                            className="launch-captain"
                            title={`Captain @${captain.handle}`}
                            data-test="launch-captain"
                        >
                            <UserAvatar
                                user={captain}
                                className="size-6 rounded-full"
                                pixels={48}
                            />
                            <span
                                className="launch-captain-hat"
                                aria-hidden="true"
                            />
                            <span className="sr-only">
                                Captain @{captain.handle}
                            </span>
                        </span>
                    )}
                    {decks
                        .filter((deck) => deck > 0)
                        .map((deck) => (
                            <span
                                key={deck}
                                className="launch-passenger-deck"
                                style={{ bottom: `${39 + deck * 42}px` }}
                                aria-hidden="true"
                            />
                        ))}
                    <ul
                        aria-label="Developers aboard"
                        className="launch-passengers"
                    >
                        {shown.map((passenger, index) => (
                            <li
                                key={passenger.id}
                                className="launch-passenger-slot"
                                title={`@${passenger.handle} is aboard`}
                                style={
                                    {
                                        left: `${12 + (index % 6) * 26}px`,
                                        bottom: `${39 + Math.floor(index / 6) * 42}px`,
                                        '--passenger-shirt': [
                                            '#5b63d3',
                                            '#d3666f',
                                            '#2d8b78',
                                            '#c48727',
                                            '#8059a2',
                                            '#2f80af',
                                        ][index % 6],
                                    } as CSSProperties
                                }
                                data-test="launch-passenger"
                            >
                                <span className="launch-person">
                                    <UserAvatar
                                        user={passenger}
                                        className="launch-passenger-face size-6 rounded-full"
                                        pixels={48}
                                    />
                                </span>
                                <span className="sr-only">
                                    @{passenger.handle}
                                </span>
                            </li>
                        ))}
                    </ul>
                    {decks.map((deck) => (
                        <span
                            key={deck}
                            className="launch-passenger-railing"
                            style={{ bottom: `${41 + deck * 42}px` }}
                            aria-hidden="true"
                        />
                    ))}
                    {excess > 0 && (
                        <span
                            className="launch-excess"
                            title={`${excess} more developers aboard`}
                        >
                            <Users className="size-3.5" aria-hidden="true" />
                            <span>+{excess} aboard</span>
                        </span>
                    )}
                    <span className="launch-smoke" aria-hidden="true" />
                    <span
                        className="launch-smoke launch-smoke-later"
                        aria-hidden="true"
                    />
                </div>
            </div>
            <svg
                className="launch-waves"
                width="100%"
                height="32"
                aria-hidden="true"
            >
                <defs>
                    <pattern
                        id={waveId}
                        width="80"
                        height="32"
                        patternUnits="userSpaceOnUse"
                    >
                        <path
                            d="M-20 10Q0-3 20 10T60 10T100 10"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                        />
                        <path
                            d="M-40 27Q-20 14 0 27T40 27T80 27"
                            fill="none"
                            stroke="currentColor"
                            opacity=".35"
                        />
                    </pattern>
                </defs>
                <rect width="100%" height="32" fill={`url(#${waveId})`} />
            </svg>
        </div>
    );
}
