import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import {
    ThreadReplyComposer,
    useThread,
} from '../../../resources/js/components/commit-thread';
import { TooltipProvider } from '../../../resources/js/components/ui/tooltip';
import type { Commit } from '../../../resources/js/types';

const commit = {
    id: 'own-commit',
    user_id: 'author',
    user: { id: 'author', handle: 'author', name: 'Author' },
    mentions: {
        author: 'author',
        excluded: 'excluded-user',
        retained: 'retained-user',
    },
    replies_count: 0,
    status: 'approved',
} as unknown as Commit;

function Composer() {
    const thread = useThread(commit);
    return (
        <TooltipProvider>
            <ThreadReplyComposer thread={{ ...thread, isReplying: true }} />
        </TooltipProvider>
    );
}

await createInertiaApp({
    page: {
        component: 'test',
        props: {
            auth: { user: commit.user, log: {} },
            errors: {},
            repliesPerPage: 3,
        },
        url: '/',
        version: null,
        clearHistory: false,
        encryptHistory: false,
        rescuedProps: [],
        rememberedState: {},
        flash: {},
    },
    resolve: () => Composer,
    setup: ({ el, App, props }) => {
        if (el) createRoot(el).render(<App {...props} />);
    },
});
