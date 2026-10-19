import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { SegmentedControl } from '@/components/segmented-control';
import { setCookie } from '@/hooks/use-appearance';
import { withViewTransition } from '@/lib/utils';

const counts = [1, 3, 5, 10, 15, 20, 25];

export default function RepliesPerPageTabs() {
    const { repliesPerPage } = usePage().props;
    const [selected, setSelected] = useState(repliesPerPage);

    const select = (value: number): void => {
        if (value === selected) {
            return;
        }

        void withViewTransition(() => setSelected(value)).then(() => {
            setCookie('replies_per_page', String(value));
            router.flushAll();
            router.reload({ only: ['repliesPerPage'] });
        });
    };

    return (
        <SegmentedControl
            label="Replies"
            name="replies-per-page"
            options={counts.map((count) => ({
                value: count,
                label: String(count),
            }))}
            value={selected}
            onChange={select}
            className="tabular-nums"
        />
    );
}
