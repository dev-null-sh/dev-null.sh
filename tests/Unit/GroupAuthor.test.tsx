import assert from 'node:assert/strict';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { GroupAuthor } from '../../resources/js/components/chat/conversation-thread';
import type { User } from '../../resources/js/types';

test('an org message author links to their profile', () => {
    const html = renderToString(
        <GroupAuthor
            author={
                {
                    id: 'author',
                    name: 'Ada Lovelace',
                    username: 'ada',
                    avatar_url: null,
                    lane: 1,
                } as User
            }
        />,
    );

    assert.match(html, /<a[^>]*href="[^"]*\/@ada"/);
    assert.match(html, /Ada Lovelace/);
});
