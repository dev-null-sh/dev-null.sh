import { Users } from 'lucide-react';

export function CommunityNote() {
    return (
        <aside
            aria-label="Community note"
            className="mt-3 rounded-lg border px-3 py-2 text-sm"
            data-test="community-note"
        >
            <p className="flex items-center gap-1.5 font-mono text-[13px] text-muted-foreground">
                <Users aria-hidden className="size-3.5 shrink-0" />
                Readers added context
            </p>
            <p className="mt-1 text-[15px]">PHP is the GOAT, actually.</p>
        </aside>
    );
}
