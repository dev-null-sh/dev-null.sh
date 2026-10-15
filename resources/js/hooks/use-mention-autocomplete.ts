import { usePage } from '@inertiajs/react';
import type { KeyboardEvent, RefObject } from 'react';
import { useEffect, useState } from 'react';
import UserSearchController from '@/actions/App/Http/Controllers/UserSearchController';
import {
    getActiveMention,
    insertMention,
    type ActiveMention,
} from '@/lib/mentions';
import type { User } from '@/types';

const debounceMs = 120;

export function useMentionAutocomplete({
    value,
    onChange,
    textareaRef,
}: {
    value: string;
    onChange: (value: string) => void;
    textareaRef: RefObject<HTMLTextAreaElement | null>;
}) {
    const { auth } = usePage().props;
    const [mention, setMention] = useState<ActiveMention | null>(null);
    const [results, setResults] = useState<User[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);

    const query = mention?.query ?? null;

    useEffect(() => {
        if (query === null) {
            setResults([]);
            setIsLoading(false);

            return;
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => {
            setIsLoading(true);

            fetch(UserSearchController.index({ query: { q: query } }).url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
            })
                .then((response) => response.json())
                .then((data: { users: User[] }) => {
                    setResults(
                        data.users.filter((user) => user.id !== auth.user.id),
                    );
                    setActiveIndex(0);
                })
                .catch(() => undefined)
                .finally(() => setIsLoading(false));
        }, debounceMs);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [query, auth.user.id]);

    const refreshMention = (text: string, caret: number) => {
        setMention(getActiveMention(text, caret));
    };

    const close = () => {
        setMention(null);
        setResults([]);
    };

    const select = (user: User) => {
        if (!mention) {
            return;
        }

        const { value: next, caret } = insertMention(
            value,
            mention,
            user.username,
        );
        onChange(next);
        close();

        requestAnimationFrame(() => {
            const textarea = textareaRef.current;

            if (!textarea) {
                return;
            }

            textarea.focus();
            textarea.setSelectionRange(caret, caret);
        });
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (!mention) {
            return false;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();

            return true;
        }

        if (results.length === 0) {
            return false;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActiveIndex(
                (index) => (index + step + results.length) % results.length,
            );

            return true;
        }

        if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            select(results[activeIndex] ?? results[0]);

            return true;
        }

        return false;
    };

    return {
        isOpen: mention !== null,
        results,
        isLoading,
        activeIndex,
        setActiveIndex,
        select,
        close,
        refreshMention,
        onKeyDown,
    } as const;
}
