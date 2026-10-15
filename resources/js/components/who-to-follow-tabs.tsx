import type { LucideIcon } from 'lucide-react';
import { Eye, EyeOff } from 'lucide-react';
import { useWhoToFollow } from '@/hooks/use-who-to-follow';
import { cn } from '@/lib/utils';

export default function WhoToFollowTabs() {
    const { showsWhoToFollow, setShowsWhoToFollow } = useWhoToFollow();

    const tabs: { value: boolean; icon: LucideIcon; label: string }[] = [
        { value: true, icon: Eye, label: 'Show' },
        { value: false, icon: EyeOff, label: 'Hide' },
    ];

    return (
        <div
            role="radiogroup"
            aria-label="Who to follow"
            className="inline-flex gap-1 rounded-lg border p-1"
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={label}
                    type="button"
                    role="radio"
                    aria-checked={showsWhoToFollow === value}
                    onClick={() => setShowsWhoToFollow(value)}
                    className={cn(
                        'flex items-center gap-2 rounded-md px-3.5 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        {
                            'bg-primary-soft text-primary hover:bg-primary-soft hover:text-primary':
                                showsWhoToFollow === value,
                        },
                    )}
                >
                    <Icon className="size-4" />
                    {label}
                </button>
            ))}
        </div>
    );
}
