import { Link } from '@inertiajs/react';
import { useRef } from 'react';
import { toast } from 'sonner';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { emojify } from '@/lib/emoji';
import { shortHash } from '@/lib/utils';
import type { CommittedFlash } from '@/types';

/**
 * How far the pointer may travel between press and release for it to still count as a click, not a swipe.
 */
const clickSlop = 5;

export function CommittedToast({
    committed,
    toastId,
}: {
    committed: CommittedFlash;
    toastId: string | number;
}) {
    const pressedAt = useRef<{ x: number; y: number } | null>(null);

    return (
        <Link
            href={CommitController.show({
                user: committed.user,
                commit: committed,
            })}
            title={committed.body}
            draggable={false}
            onPointerDown={(event) => {
                pressedAt.current = { x: event.clientX, y: event.clientY };
            }}
            onClick={(event) => {
                const pressed = pressedAt.current;
                pressedAt.current = null;

                // Sonner captures the pointer while the toast is swiped, so releasing it still clicks the link.
                // Keyboard clicks have no pointer position, and a press left over from a touch swipe is not theirs.
                if (
                    event.detail > 0 &&
                    pressed &&
                    Math.hypot(
                        event.clientX - pressed.x,
                        event.clientY - pressed.y,
                    ) > clickSlop
                ) {
                    event.preventDefault();
                }
            }}
            onStart={() => toast.dismiss(toastId)}
            className="line-clamp-3 font-mono text-[13px] wrap-anywhere after:absolute after:inset-0 after:rounded-lg focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-ring"
            data-test="committed-toast"
        >
            [log {shortHash(committed.id)}] {emojify(committed.body)}
        </Link>
    );
}
