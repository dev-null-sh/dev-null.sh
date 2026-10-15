import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { cn, laneColor } from '@/lib/utils';
import type { User } from '@/types';

export function MentionSuggestions({
    results,
    isLoading,
    activeIndex,
    onActiveIndexChange,
    onSelect,
}: {
    results: User[];
    isLoading: boolean;
    activeIndex: number;
    onActiveIndexChange: (index: number) => void;
    onSelect: (user: User) => void;
}) {
    if (!isLoading && results.length === 0) {
        return (
            <div
                role="status"
                className="absolute z-20 mt-1 w-full max-w-sm rounded-lg border bg-popover px-3 py-2 text-sm text-muted-foreground shadow-md"
            >
                No developers match.
            </div>
        );
    }

    return (
        <ul
            id="mention-suggestions"
            role="listbox"
            aria-label="Mention suggestions"
            className="absolute z-20 mt-1 max-h-64 w-full max-w-sm overflow-y-auto rounded-lg border bg-popover p-1 shadow-md"
            data-test="mention-suggestions"
        >
            {isLoading && results.length === 0 ? (
                <li className="flex justify-center py-3">
                    <Spinner className="size-4 text-muted-foreground" />
                </li>
            ) : (
                results.map((user, index) => (
                    <li
                        key={user.id}
                        role="option"
                        aria-selected={index === activeIndex}
                    >
                        <button
                            type="button"
                            tabIndex={-1}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                onSelect(user);
                            }}
                            onMouseMove={() => onActiveIndexChange(index)}
                            className={cn(
                                'flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left',
                                {
                                    'bg-accent': index === activeIndex,
                                },
                            )}
                        >
                            <UserAvatar
                                user={user}
                                className="size-8 shrink-0"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="flex items-baseline gap-1.5">
                                    <span className="truncate text-sm font-semibold">
                                        {user.name}
                                    </span>
                                    <span className="flex shrink-0 items-center gap-1 font-mono text-[12px] text-muted-foreground">
                                        <span
                                            aria-hidden
                                            className="size-1.5 rounded-full"
                                            style={{
                                                background: laneColor(user.lane),
                                            }}
                                        />
                                        @{user.username}
                                    </span>
                                </span>
                            </span>
                        </button>
                    </li>
                ))
            )}
        </ul>
    );
}
