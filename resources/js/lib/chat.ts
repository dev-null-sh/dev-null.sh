import { router } from '@inertiajs/react';
import BannedController from '@/actions/App/Http/Controllers/BannedController';
import ConversationArchiveController from '@/actions/App/Http/Controllers/ConversationArchiveController';
import ConversationDropController from '@/actions/App/Http/Controllers/ConversationDropController';
import ConversationMergeController from '@/actions/App/Http/Controllers/ConversationMergeController';
import ConversationMessageController from '@/actions/App/Http/Controllers/ConversationMessageController';
import ConversationReadController from '@/actions/App/Http/Controllers/ConversationReadController';
import ConversationTypingController from '@/actions/App/Http/Controllers/ConversationTypingController';
import InboxController from '@/actions/App/Http/Controllers/InboxController';
import MessageReactionController from '@/actions/App/Http/Controllers/MessageReactionController';
import OrgArchiveController from '@/actions/App/Http/Controllers/OrgArchiveController';
import OrgController from '@/actions/App/Http/Controllers/OrgController';
import OrgMemberController from '@/actions/App/Http/Controllers/OrgMemberController';
import OrgMessageController from '@/actions/App/Http/Controllers/OrgMessageController';
import OrgReadController from '@/actions/App/Http/Controllers/OrgReadController';
import OrgTypingController from '@/actions/App/Http/Controllers/OrgTypingController';
import RemoteCandidateController from '@/actions/App/Http/Controllers/RemoteCandidateController';
import UserBlockController from '@/actions/App/Http/Controllers/UserBlockController';
import { showTimeout, timeoutFrom } from '@/components/timeout-dialog';
import type { ReactionUsage } from '@/lib/reactions';
import { withReactionUse } from '@/lib/reactions';
import type { VoiceDraft } from '@/lib/voice-notes';
import type {
    ConversationSummary,
    InboxPage,
    InboxTab,
    Message,
    MessageReaction,
    MessageReactionChange,
    MessageReply,
    OrgMember,
    OrgRef,
    OrgRole,
    OrgThread,
    PushAccess,
    Thread,
    User,
} from '@/types';

/**
 * Who a conversation is with: one person, or an org.
 */
export type ChatTarget =
    | { kind: 'direct'; partner: User }
    | { kind: 'org'; org: OrgRef };

/**
 * What the composer pushes: the body, the message it replies to, its images, the link chosen for its card, which
 * is the first link when undefined, or none when null, and a voice note, which goes without a body or images.
 */
export type MessageDraft = {
    body: string;
    replyTo: MessageReply | null;
    images: File[];
    descriptions: string[];
    linkPreviewUrl: string | null | undefined;
    voice?: VoiceDraft | null;
};

export type PendingMessage = {
    client_id: string;
    body: string;
    reply_to: MessageReply | null;
    images: File[];
    image_descriptions: string[];
    link_preview_url: string | null | undefined;
    voice: VoiceDraft | null;
    created_at: string;
    status: 'staged' | 'rejected';
};

export type ThreadState = {
    target: ChatTarget;
    messages: Message[];
    has_older: boolean;
    read_through_id: number;
    partner_read_through_id: number;
    partner_read_at: string | null;
    partner_typing: boolean;
    members: OrgMember[];
    typing: string[];
    role: OrgRole | null;
    is_archived: boolean;
    access: PushAccess;
    pending: PendingMessage[];
    /**
     * The conversation's reactions version the thread is current with, which polls ask for the changes after. Null
     * until the server has handed one over.
     */
    reactions_version: number | null;
    /**
     * The message the composer is replying to, kept while the user moves between the dock and Remotes.
     */
    replyingTo: MessageReply | null;
    /**
     * Where the user had read up to when they opened the conversation, kept while it stays open.
     */
    head: number;
    /**
     * The latest message when the conversation was opened: replies arriving while it is open are not behind HEAD.
     */
    headUntil: number;
    isLoaded: boolean;
    isLoadingOlder: boolean;
};

export type ChatState = {
    conversations: ConversationSummary[] | null;
    fetched: ConversationSummary[] | null;
    archived: ConversationSummary[] | null;
    hasMore: Record<InboxTab, boolean>;
    isLoadingMore: Record<InboxTab, boolean>;
    unread: number | null;
    requests: number | null;
    isPollingInbox: boolean;
    threads: Record<string, ThreadState>;
    /**
     * The emoji the user reacts with, most used first, loaded once something shows a conversation.
     */
    reactionUsage: ReactionUsage[] | null;
};

export type Candidate = { user: User; reason: string | null };

const activePoll = 1_500;
const idlePoll = 4_000;
const inboxPoll = 15_000;
const busyInboxPoll = 6_000;
const activeWindow = 60_000;
const typingPing = 3_000;

const listOf = {
    open: 'conversations',
    fetched: 'fetched',
    stashed: 'archived',
} as const;

let state: ChatState = {
    conversations: null,
    fetched: null,
    archived: null,
    hasMore: { open: false, fetched: false, stashed: false },
    isLoadingMore: { open: false, fetched: false, stashed: false },
    unread: null,
    requests: null,
    isPollingInbox: false,
    threads: {},
    reactionUsage: null,
};
const listeners = new Set<() => void>();
const threadWatchers = new Map<string, number>();
const lastActivity = new Map<string, number>();
const lastTypingPing = new Map<string, number>();
const reactionsInFlight = new Map<number, number>();
const reactionsSettledAt = new Map<number, number>();
let inboxWatchers = 0;
let inboxTimer: number | undefined;
let isLoadingReactionUsage = false;

export function targetKey(target: ChatTarget): string {
    return target.kind === 'direct'
        ? target.partner.username
        : `org:${target.org.id}`;
}

export function summaryKey(summary: ConversationSummary): string {
    return summary.kind === 'org' || summary.partner === null
        ? `org:${summary.id}`
        : summary.partner.username;
}

export function targetOf(summary: ConversationSummary): ChatTarget {
    return summary.kind === 'org' || summary.partner === null
        ? { kind: 'org', org: { id: summary.id, name: summary.name } }
        : { kind: 'direct', partner: summary.partner };
}

function urls(target: ChatTarget) {
    if (target.kind === 'direct') {
        const partner = { partner: target.partner.username };

        return {
            index: (query?: Record<string, number>) =>
                ConversationMessageController.index.url(
                    partner,
                    query ? { query } : undefined,
                ),
            store: ConversationMessageController.store.url(partner),
            read: ConversationReadController.update.url(partner),
            typing: ConversationTypingController.update.url(partner),
            stash: ConversationArchiveController.store.url(partner),
            pop: ConversationArchiveController.destroy.url(partner),
        };
    }

    const org = { org: target.org.id };

    return {
        index: (query?: Record<string, number>) =>
            OrgMessageController.index.url(org, query ? { query } : undefined),
        store: OrgMessageController.store.url(org),
        read: OrgReadController.update.url(org),
        typing: OrgTypingController.update.url(org),
        stash: OrgArchiveController.store.url(org),
        pop: OrgArchiveController.destroy.url(org),
    };
}

export function subscribeToChat(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export function chatSnapshot(): ChatState {
    return state;
}

function update(next: Partial<ChatState>): void {
    state = { ...state, ...next };
    listeners.forEach((listener) => listener());
}

/**
 * Pages seed the store while rendering, so the change reaches other subscribers just after, not during, render.
 */
function updateDuringRender(next: Partial<ChatState>): void {
    state = { ...state, ...next };
    queueMicrotask(() => listeners.forEach((listener) => listener()));
}

function updateThread(
    key: string,
    change: (thread: ThreadState) => Partial<ThreadState>,
): void {
    const thread = state.threads[key];

    if (thread === undefined) {
        return;
    }

    update({
        threads: {
            ...state.threads,
            [key]: { ...thread, ...change(thread) },
        },
    });
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export class ChatRequestError extends Error {
    constructor(
        public status: number,
        public body: { message?: string; errors?: Record<string, string[]> },
    ) {
        super(body.message ?? `Chat request failed: ${status}`);
    }

    /**
     * The first reason the server gave, for showing next to whatever was refused.
     */
    firstError(): string {
        return Object.values(this.body.errors ?? {})[0]?.[0] ?? this.message;
    }
}

/**
 * Sends JSON, or a form when there are files in it, which gets longer to upload.
 */
async function request<T>(
    url: string,
    method: 'GET' | 'POST' | 'PUT' | 'DELETE' = 'GET',
    body?: object | FormData,
): Promise<T> {
    const isForm = body instanceof FormData;
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            ...(isForm ? {} : { 'Content-Type': 'application/json' }),
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        ...(body === undefined
            ? {}
            : { body: isForm ? body : JSON.stringify(body) }),
        cache: 'no-store',
        credentials: 'same-origin',
        signal: AbortSignal.timeout(isForm ? 60_000 : 10_000),
    });

    if (!response.ok) {
        const body = (await response.json().catch(() => ({}))) as {
            message?: string;
            banned?: boolean;
        };
        const timeout = timeoutFrom(response.status, body);

        // A timeout or a ban stops every remote the same way, so the user is told wherever they tried...
        if (timeout) {
            showTimeout(timeout);
        } else if (response.status === 403 && body.banned === true) {
            router.visit(BannedController.show());
        }

        throw new ChatRequestError(response.status, body);
    }

    return (response.status === 204 ? null : await response.json()) as T;
}

function mergeMessages(current: Message[], incoming: Message[]): Message[] {
    if (incoming.length === 0) {
        return current;
    }

    const byId = new Map(current.map((message) => [message.id, message]));
    incoming.forEach((message) => byId.set(message.id, message));

    return [...byId.values()].sort((a, b) => a.id - b.id);
}

/**
 * Swaps in the reactions that changed, except on messages the user is reacting to right now, or reacted to after the
 * poll was sent: their own request hands back fresher reactions.
 */
function withReactionChanges(
    messages: Message[],
    changes: MessageReactionChange[],
    polledAt: number,
): Message[] {
    const changed = new Map(
        changes
            .filter(
                (change) =>
                    !reactionsInFlight.has(change.id) &&
                    (reactionsSettledAt.get(change.id) ?? 0) < polledAt,
            )
            .map((change) => [change.id, change.reactions]),
    );

    if (changed.size === 0) {
        return messages;
    }

    return messages.map((message) =>
        changed.has(message.id)
            ? { ...message, reactions: changed.get(message.id) }
            : message,
    );
}

function unreadOf(conversations: ConversationSummary[]): number {
    return conversations.reduce(
        (total, conversation) => total + conversation.ahead,
        0,
    );
}

function withoutKey(
    conversations: ConversationSummary[] | null,
    key: string,
): ConversationSummary[] | null {
    return (
        conversations?.filter(
            (conversation) => summaryKey(conversation) !== key,
        ) ?? null
    );
}

function byLatest(conversations: ConversationSummary[]): ConversationSummary[] {
    return [...conversations].sort((a, b) =>
        (b.last_message_at ?? '').localeCompare(a.last_message_at ?? ''),
    );
}

export function seedInbox(inbox: InboxPage): void {
    updateDuringRender({
        conversations: inbox.conversations,
        hasMore: { ...state.hasMore, open: inbox.has_more },
        unread: state.unread ?? unreadOf(inbox.conversations),
    });
}

/**
 * The first page fresh from the server, followed by the older pages already scrolled into, without duplicates.
 */
function withFirstPage(
    current: ConversationSummary[] | null,
    firstPage: ConversationSummary[],
): ConversationSummary[] {
    const fresh = new Set(firstPage.map(summaryKey));
    const oldest = firstPage.at(-1)?.last_message_at ?? '';

    return [
        ...firstPage,
        ...(current ?? []).filter(
            (item) =>
                !fresh.has(summaryKey(item)) &&
                (item.last_message_at ?? '') <= oldest,
        ),
    ];
}

async function fetchInbox(
    tab: InboxTab,
    before?: string,
): Promise<InboxPage & { unread: number; requests: number }> {
    return request(
        InboxController.index.url({
            query: { tab, ...(before === undefined ? {} : { before }) },
        }),
    );
}

/**
 * Loads a tab's first page, for the tabs that are not polled.
 */
export async function loadTab(tab: InboxTab): Promise<void> {
    try {
        const page = await fetchInbox(tab);

        update({
            [listOf[tab]]: page.conversations,
            hasMore: { ...state.hasMore, [tab]: page.has_more },
            requests: page.requests,
        });
    } catch {
        update({ [listOf[tab]]: state[listOf[tab]] ?? [] });
    }
}

/**
 * The next page of a tab, once the user scrolls to the end of it.
 */
export async function loadMoreConversations(tab: InboxTab): Promise<void> {
    const list = state[listOf[tab]];
    const last = list?.at(-1);

    if (!state.hasMore[tab] || state.isLoadingMore[tab] || last === undefined) {
        return;
    }

    update({ isLoadingMore: { ...state.isLoadingMore, [tab]: true } });

    try {
        const page = await fetchInbox(tab, last.id);
        const known = new Set((state[listOf[tab]] ?? []).map(summaryKey));

        update({
            [listOf[tab]]: [
                ...(state[listOf[tab]] ?? []),
                ...page.conversations.filter(
                    (item) => !known.has(summaryKey(item)),
                ),
            ],
            hasMore: { ...state.hasMore, [tab]: page.has_more },
        });
    } finally {
        update({ isLoadingMore: { ...state.isLoadingMore, [tab]: false } });
    }
}

function emptyThread(target: ChatTarget): ThreadState {
    return {
        target,
        messages: [],
        has_older: false,
        read_through_id: 0,
        partner_read_through_id: 0,
        partner_read_at: null,
        partner_typing: false,
        members: [],
        typing: [],
        role: null,
        is_archived: false,
        access: 'open',
        pending: [],
        reactions_version: null,
        replyingTo: null,
        head: 0,
        headUntil: 0,
        isLoaded: false,
        isLoadingOlder: false,
    };
}

function fromServer(thread: Thread | OrgThread): Partial<ThreadState> {
    return 'members' in thread
        ? {
              members: thread.members,
              typing: thread.typing,
              role: thread.role,
              is_archived: thread.is_archived,
              access: thread.access,
          }
        : {
              partner_read_through_id: thread.partner_read_through_id,
              partner_read_at: thread.partner_read_at,
              partner_typing: thread.partner_typing,
              is_archived: thread.is_archived,
              access: thread.access,
          };
}

/**
 * Pages hand over what the server already rendered, so the first paint needs no request.
 */
export function seedThread(
    target: ChatTarget,
    thread: Thread | OrgThread,
): void {
    const key = targetKey(target);
    const current = state.threads[key] ?? emptyThread(target);

    updateDuringRender({
        threads: {
            ...state.threads,
            [key]: {
                ...current,
                ...fromServer(thread),
                target,
                messages: mergeMessages(current.messages, thread.messages),
                has_older: current.isLoaded
                    ? current.has_older
                    : thread.has_older,
                read_through_id: thread.read_through_id,
                reactions_version: thread.reactions_version,
                head: thread.read_through_id,
                headUntil: thread.messages.at(-1)?.id ?? 0,
                isLoaded: true,
                isLoadingOlder: false,
            },
        },
    });
}

/**
 * Opens a conversation before anything was written in it, or before its first poll.
 */
export function rememberTarget(target: ChatTarget): void {
    const key = targetKey(target);

    if (state.threads[key] === undefined) {
        update({
            threads: { ...state.threads, [key]: emptyThread(target) },
        });
    }
}

async function pollInbox(): Promise<void> {
    if (document.hidden) {
        return;
    }

    try {
        const { conversations, unread, requests } = await fetchInbox('open');
        const requestsChanged =
            state.requests !== null && requests !== state.requests;
        const watched = conversations.filter((conversation) =>
            isWatching(summaryKey(conversation)),
        );

        update({
            conversations: withFirstPage(
                state.conversations,
                conversations.map((conversation) =>
                    isWatching(summaryKey(conversation))
                        ? { ...conversation, ahead: 0 }
                        : conversation,
                ),
            ),
            requests,
            unread:
                unread -
                watched.reduce(
                    (total, conversation) => total + conversation.ahead,
                    0,
                ),
        });

        if (requestsChanged && state.fetched !== null) {
            void loadTab('fetched');
        }
    } catch {
        // Keep the last known inbox until the next poll.
    }
}

function scheduleInbox(): void {
    window.clearTimeout(inboxTimer);

    if (inboxWatchers === 0) {
        return;
    }

    inboxTimer = window.setTimeout(
        () => void pollInbox().finally(scheduleInbox),
        threadWatchers.size > 0 ? busyInboxPoll : inboxPoll,
    );
}

function isWatching(key: string): boolean {
    return (threadWatchers.get(key) ?? 0) > 0;
}

/**
 * Keeps the inbox fresh while something shows it. Returns the function that stops watching.
 */
export function watchInbox(): () => void {
    inboxWatchers++;

    if (inboxWatchers === 1) {
        update({ isPollingInbox: true });
        void pollInbox();
        scheduleInbox();
    }

    const onVisible = () => {
        if (!document.hidden) {
            void pollInbox();
        }
    };

    document.addEventListener('visibilitychange', onVisible);

    return () => {
        inboxWatchers--;
        document.removeEventListener('visibilitychange', onVisible);
        scheduleInbox();

        if (inboxWatchers === 0) {
            update({ isPollingInbox: false });
        }
    };
}

async function pollThread(target: ChatTarget): Promise<void> {
    const key = targetKey(target);
    const thread = state.threads[key];
    const after = thread?.isLoaded ? (thread.messages.at(-1)?.id ?? 0) : null;
    const reactionsAfter =
        after === null ? null : (thread?.reactions_version ?? null);
    const polledAt = performance.now();

    const fresh = await request<
        (Thread & { partner: User }) | (OrgThread & { org: OrgRef })
    >(
        urls(target).index(
            after === null
                ? undefined
                : {
                      after,
                      ...(reactionsAfter === null
                          ? {}
                          : { reactions_after: reactionsAfter }),
                  },
        ),
    );

    const current = state.threads[key] ?? emptyThread(target);
    const isTyping =
        'members' in fresh ? fresh.typing.length > 0 : fresh.partner_typing;

    if (fresh.messages.length > 0 || isTyping) {
        lastActivity.set(key, Date.now());
    }

    update({
        threads: {
            ...state.threads,
            [key]: {
                ...current,
                ...fromServer(fresh),
                target:
                    'org' in fresh
                        ? { kind: 'org', org: fresh.org }
                        : current.isLoaded
                          ? current.target
                          : { kind: 'direct', partner: fresh.partner },
                messages: mergeMessages(
                    withReactionChanges(
                        current.messages,
                        fresh.reaction_changes,
                        polledAt,
                    ),
                    fresh.messages,
                ),
                reactions_version: Math.max(
                    current.reactions_version ?? 0,
                    fresh.reactions_version,
                ),
                pending:
                    fresh.messages.length === 0
                        ? current.pending
                        : current.pending.filter(
                              (pending) =>
                                  !fresh.messages.some(
                                      (message) =>
                                          message.client_id ===
                                          pending.client_id,
                                  ),
                          ),
                has_older: current.isLoaded
                    ? current.has_older
                    : fresh.has_older,
                read_through_id: Math.max(
                    current.read_through_id,
                    fresh.read_through_id,
                ),
                head: current.isLoaded ? current.head : fresh.read_through_id,
                headUntil: current.isLoaded
                    ? current.headUntil
                    : (fresh.messages.at(-1)?.id ?? 0),
                isLoaded: true,
            },
        },
    });
}

/**
 * Polls an open conversation quickly while it is lively and slower once it goes quiet, and not at all in a
 * hidden tab. Returns the function that stops watching.
 */
export function watchThread(target: ChatTarget): () => void {
    const key = targetKey(target);
    let timer: number | undefined;
    let isStopped = false;

    threadWatchers.set(key, (threadWatchers.get(key) ?? 0) + 1);

    if (state.threads[key] === undefined) {
        update({
            threads: { ...state.threads, [key]: emptyThread(target) },
        });
    } else {
        updateThread(key, (thread) => ({
            head: thread.isLoaded ? thread.read_through_id : thread.head,
            headUntil: thread.isLoaded
                ? (thread.messages.at(-1)?.id ?? 0)
                : thread.headUntil,
        }));
    }

    const tick = async () => {
        window.clearTimeout(timer);

        if (!document.hidden) {
            try {
                await pollThread(target);
            } catch {
                // Keep the conversation as it is until the next poll.
            }
        }

        if (isStopped) {
            return;
        }

        const isActive =
            Date.now() - (lastActivity.get(key) ?? 0) < activeWindow;

        timer = window.setTimeout(
            () => void tick(),
            isActive ? activePoll : idlePoll,
        );
    };

    const onVisible = () => {
        if (!document.hidden) {
            void tick();
        }
    };

    document.addEventListener('visibilitychange', onVisible);
    void tick();
    scheduleInbox();
    void loadReactionUsage();

    return () => {
        isStopped = true;
        window.clearTimeout(timer);
        document.removeEventListener('visibilitychange', onVisible);

        const watchers = (threadWatchers.get(key) ?? 1) - 1;

        if (watchers === 0) {
            threadWatchers.delete(key);
        } else {
            threadWatchers.set(key, watchers);
        }

        scheduleInbox();
    };
}

function touchInbox(target: ChatTarget, message: Message): void {
    const key = targetKey(target);
    const conversations = state.conversations ?? [];
    const existing = [...conversations, ...(state.archived ?? [])].find(
        (conversation) => summaryKey(conversation) === key,
    );

    if (existing === undefined && target.kind === 'org') {
        void pollInbox();

        return;
    }

    const summary: ConversationSummary = {
        id: existing?.id ?? '',
        kind: target.kind,
        name: existing?.name ?? null,
        partner: target.kind === 'direct' ? target.partner : null,
        members: existing?.members ?? null,
        last_message: message,
        last_message_at: message.created_at,
        ahead: existing?.ahead ?? 0,
        partner_read_through_id: existing?.partner_read_through_id ?? 0,
        partner_read_at: existing?.partner_read_at ?? null,
        is_archived: false,
    };

    update({
        conversations: [summary, ...(withoutKey(conversations, key) ?? [])],
        archived: withoutKey(state.archived, key),
    });
    updateThread(key, () => ({ is_archived: false }));
}

async function deliver(
    target: ChatTarget,
    pending: PendingMessage,
): Promise<void> {
    const key = targetKey(target);

    try {
        const { message } = await request<{ message: Message }>(
            urls(target).store,
            'POST',
            payloadOf(pending),
        );

        lastActivity.set(key, Date.now());

        if (state.threads[key]?.messages.length === 0) {
            void pollThread(target).catch(() => undefined);
        }

        updateThread(key, (thread) => ({
            messages: mergeMessages(thread.messages, [message]),
            pending: thread.pending.filter(
                (item) => item.client_id !== pending.client_id,
            ),
            read_through_id: Math.max(thread.read_through_id, message.id),
            head: Math.max(thread.head, message.id),
        }));
        touchInbox(target, message);
    } catch {
        updateThread(key, (thread) => ({
            pending: thread.pending.map((item) =>
                item.client_id === pending.client_id
                    ? { ...item, status: 'rejected' }
                    : item,
            ),
        }));
    }
}

function payloadOf(pending: PendingMessage): object | FormData {
    const fields = {
        body: pending.body,
        client_id: pending.client_id,
        ...(pending.reply_to === null
            ? {}
            : { reply_to_id: pending.reply_to.id }),
        ...(pending.link_preview_url === undefined
            ? {}
            : { link_preview_url: pending.link_preview_url ?? '' }),
    };

    if (pending.images.length === 0 && pending.voice === null) {
        return fields;
    }

    const form = new FormData();

    Object.entries(fields).forEach(([name, value]) =>
        form.append(name, String(value)),
    );
    pending.images.forEach((image, index) => {
        form.append('images[]', image);
        form.append(
            `image_descriptions[${index}]`,
            pending.image_descriptions[index]?.trim() ?? '',
        );
    });

    if (pending.voice !== null) {
        form.append('voice', pending.voice.file);
        pending.voice.waveform.forEach((level) =>
            form.append('voice_waveform[]', String(level)),
        );
    }

    return form;
}

/**
 * Shows the message straight away as staged, then swaps in the stored message once the server has it.
 */
export function sendMessage(target: ChatTarget, draft: MessageDraft): void {
    const key = targetKey(target);

    rememberTarget(target);

    const pending: PendingMessage = {
        client_id: crypto.randomUUID(),
        body: draft.body,
        reply_to: draft.replyTo,
        images: draft.images,
        image_descriptions: draft.descriptions,
        link_preview_url: draft.linkPreviewUrl,
        voice: draft.voice ?? null,
        created_at: new Date().toISOString(),
        status: 'staged',
    };

    lastTypingPing.delete(key);
    updateThread(key, (thread) => ({
        pending: [...thread.pending, pending],
    }));
    void deliver(target, pending);
}

async function loadReactionUsage(): Promise<void> {
    if (state.reactionUsage !== null || isLoadingReactionUsage) {
        return;
    }

    isLoadingReactionUsage = true;

    try {
        const { usage } = await request<{ usage: ReactionUsage[] }>(
            MessageReactionController.index.url(),
        );

        update({ reactionUsage: usage });
    } catch {
        // The picker falls back to the default quick reactions.
    } finally {
        isLoadingReactionUsage = false;
    }
}

function countReactionUse(emoji: string, change: 1 | -1): void {
    if (state.reactionUsage !== null) {
        update({
            reactionUsage: withReactionUse(state.reactionUsage, emoji, change),
        });
    }
}

function setReactions(
    key: string,
    messageId: number,
    change: (reactions: MessageReaction[]) => MessageReaction[],
): void {
    updateThread(key, (thread) => ({
        messages: thread.messages.map((message) =>
            message.id === messageId
                ? { ...message, reactions: change(message.reactions ?? []) }
                : message,
        ),
    }));
}

/**
 * Adds the user's reaction, or takes it back when they already left it, straight away, then settles on what the
 * server holds. Returns whether the server took it.
 */
export async function toggleReaction(
    target: ChatTarget,
    messageId: number,
    emoji: string,
    userId: string,
): Promise<boolean> {
    const key = targetKey(target);
    const message = state.threads[key]?.messages.find(
        (item) => item.id === messageId,
    );

    if (message === undefined) {
        return false;
    }

    const isMine = (reaction: MessageReaction) =>
        reaction.user_id === userId && reaction.emoji === emoji;
    const isRemoving = (message.reactions ?? []).some(isMine);
    const add = (reactions: MessageReaction[]) =>
        reactions.some(isMine)
            ? reactions
            : [...reactions, { user_id: userId, emoji }];
    const remove = (reactions: MessageReaction[]) =>
        reactions.filter((reaction) => !isMine(reaction));
    const settle = () => {
        const left = (reactionsInFlight.get(messageId) ?? 1) - 1;

        reactionsSettledAt.set(messageId, performance.now());

        if (left === 0) {
            reactionsInFlight.delete(messageId);
        } else {
            reactionsInFlight.set(messageId, left);
        }

        return left === 0;
    };

    reactionsInFlight.set(
        messageId,
        (reactionsInFlight.get(messageId) ?? 0) + 1,
    );
    setReactions(key, messageId, isRemoving ? remove : add);
    countReactionUse(emoji, isRemoving ? -1 : 1);

    try {
        const { reactions } = await request<{ reactions: MessageReaction[] }>(
            isRemoving
                ? MessageReactionController.destroy.url({ message: messageId })
                : MessageReactionController.store.url({ message: messageId }),
            isRemoving ? 'DELETE' : 'POST',
            { emoji },
        );

        if (settle()) {
            setReactions(key, messageId, () => reactions);
        }

        return true;
    } catch {
        settle();
        setReactions(key, messageId, isRemoving ? add : remove);
        countReactionUse(emoji, isRemoving ? 1 : -1);

        return false;
    }
}

export function setReplyingTo(
    target: ChatTarget,
    reply: MessageReply | null,
): void {
    updateThread(targetKey(target), () => ({ replyingTo: reply }));
}

/**
 * Sends a rejected message again with its original client id, so the server stores it once at most.
 */
export function retryMessage(target: ChatTarget, clientId: string): void {
    const key = targetKey(target);
    const pending = state.threads[key]?.pending.find(
        (item) => item.client_id === clientId,
    );

    if (pending === undefined) {
        return;
    }

    updateThread(key, (thread) => ({
        pending: thread.pending.map((item) =>
            item.client_id === clientId ? { ...item, status: 'staged' } : item,
        ),
    }));
    void deliver(target, { ...pending, status: 'staged' });
}

export function markThreadRead(target: ChatTarget): void {
    const key = targetKey(target);
    const thread = state.threads[key];
    const latest = thread?.messages.at(-1)?.id ?? 0;

    if (thread === undefined || latest <= thread.read_through_id) {
        return;
    }

    updateThread(key, () => ({ read_through_id: latest }));

    const conversations = (state.conversations ?? []).map((conversation) =>
        summaryKey(conversation) === key
            ? { ...conversation, ahead: 0 }
            : conversation,
    );

    update({ conversations, unread: unreadOf(conversations) });

    void request(urls(target).read, 'PUT', { through: latest }).catch(
        () => undefined,
    );
}

export function pingTyping(target: ChatTarget): void {
    const key = targetKey(target);
    const now = Date.now();

    if (now - (lastTypingPing.get(key) ?? 0) < typingPing) {
        return;
    }

    lastTypingPing.set(key, now);
    void request(urls(target).typing, 'PUT').catch(() => undefined);
}

export async function loadOlderMessages(target: ChatTarget): Promise<void> {
    const key = targetKey(target);
    const thread = state.threads[key];
    const oldest = thread?.messages[0]?.id;

    if (
        thread === undefined ||
        oldest === undefined ||
        !thread.has_older ||
        thread.isLoadingOlder
    ) {
        return;
    }

    updateThread(key, () => ({ isLoadingOlder: true }));

    try {
        const older = await request<Thread | OrgThread>(
            urls(target).index({ before: oldest }),
        );

        updateThread(key, (current) => ({
            messages: mergeMessages(current.messages, older.messages),
            has_older: older.has_older,
            isLoadingOlder: false,
        }));
    } catch {
        updateThread(key, () => ({ isLoadingOlder: false }));
    }
}

/**
 * Searches the user's conversations by a partner's or member's name or handle, or an org's name, on whichever tab
 * is open.
 */
export async function searchConversations(
    term: string,
    tab: InboxTab,
    signal: AbortSignal,
): Promise<ConversationSummary[]> {
    const response = await fetch(
        InboxController.index.url({ query: { q: term, tab } }),
        { headers: { Accept: 'application/json' }, cache: 'no-store', signal },
    );

    if (!response.ok) {
        throw new Error(`Conversation search failed: ${response.status}`);
    }

    return ((await response.json()) as InboxPage).conversations;
}

/**
 * Developers to message or add to an org, each with the reason they can't be, if there is one. Those who can be
 * picked come first.
 */
export async function searchCandidates(
    term: string,
    scope: { for: 'remote' | 'org' } | { org: string },
    signal: AbortSignal,
): Promise<Candidate[]> {
    const response = await fetch(
        RemoteCandidateController.index.url({ query: { q: term, ...scope } }),
        { headers: { Accept: 'application/json' }, cache: 'no-store', signal },
    );

    if (!response.ok) {
        return [];
    }

    const { candidates } = (await response.json()) as {
        candidates: Candidate[];
    };

    return candidates.toSorted(
        (a, b) => Number(a.reason !== null) - Number(b.reason !== null),
    );
}

/**
 * Merges a fetched request into the open conversations, or drops it. The sender is never told about a drop.
 */
export async function settleRequest(
    partner: User,
    isMerging: boolean,
): Promise<boolean> {
    const key = partner.username;

    try {
        await request(
            isMerging
                ? ConversationMergeController.store.url({ partner: key })
                : ConversationDropController.store.url({ partner: key }),
            'POST',
        );
    } catch {
        return false;
    }

    const fetchedRequest = state.fetched?.find(
        (item) => summaryKey(item) === key,
    );

    update({
        fetched: withoutKey(state.fetched, key),
        requests: Math.max(0, (state.requests ?? 1) - 1),
        unread: Math.max(0, (state.unread ?? 1) - 1),
        ...(isMerging && fetchedRequest
            ? {
                  conversations: byLatest([
                      ...(withoutKey(state.conversations, key) ?? []),
                      { ...fetchedRequest, ahead: 0 },
                  ]),
              }
            : {}),
    });
    updateThread(key, () => ({ access: isMerging ? 'open' : 'waiting' }));

    return true;
}

/**
 * Moves the conversation between the inbox and the stash straight away, and back if the server refuses.
 */
export async function setConversationArchived(
    target: ChatTarget,
    isArchived: boolean,
): Promise<boolean> {
    const key = targetKey(target);
    const previous = {
        conversations: state.conversations,
        archived: state.archived,
        unread: state.unread,
    };
    const conversation = [
        ...(state.conversations ?? []),
        ...(state.archived ?? []),
    ].find((item) => summaryKey(item) === key);

    if (conversation !== undefined) {
        const moved = { ...conversation, is_archived: isArchived };
        const conversations = isArchived
            ? withoutKey(state.conversations, key)
            : byLatest([
                  ...(withoutKey(state.conversations, key) ?? []),
                  moved,
              ]);
        const archived = isArchived
            ? state.archived &&
              byLatest([...(withoutKey(state.archived, key) ?? []), moved])
            : withoutKey(state.archived, key);

        update({
            conversations,
            archived,
            unread:
                state.unread === null
                    ? null
                    : state.unread +
                      (isArchived ? -conversation.ahead : conversation.ahead),
        });
    }

    updateThread(key, () => ({ is_archived: isArchived }));

    try {
        await request(
            isArchived ? urls(target).stash : urls(target).pop,
            isArchived ? 'POST' : 'DELETE',
        );

        return true;
    } catch {
        update(previous);
        updateThread(key, () => ({ is_archived: !isArchived }));

        return false;
    }
}

/**
 * Blocks through the same endpoint as profiles. Its redirect back to the conversation is not followed, since
 * that conversation no longer exists for either of them.
 */
export async function blockPartner(partner: User): Promise<boolean> {
    const response = await fetch(UserBlockController.store.url(partner), {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        redirect: 'manual',
    }).catch(() => null);

    if (
        response === null ||
        (response.type !== 'opaqueredirect' && !response.ok)
    ) {
        return false;
    }

    const threads = { ...state.threads };
    delete threads[partner.username];

    update({
        conversations: withoutKey(state.conversations, partner.username),
        fetched: withoutKey(state.fetched, partner.username),
        archived: withoutKey(state.archived, partner.username),
        threads,
    });

    return true;
}

/**
 * Starts an org with the people picked, and returns it.
 */
export async function createOrg(
    name: string,
    members: string[],
): Promise<OrgRef> {
    const { org } = await request<{ org: { id: string } }>(
        OrgController.store.url(),
        'POST',
        { name, members },
    );

    void pollInbox();

    return { id: org.id, name: name.trim() || null };
}

async function refreshOrg(org: OrgRef): Promise<void> {
    await pollThread({ kind: 'org', org }).catch(() => undefined);
    void pollInbox();
}

export async function renameOrg(org: OrgRef, name: string): Promise<void> {
    await request(OrgController.update.url({ org: org.id }), 'PUT', { name });

    const key = targetKey({ kind: 'org', org });
    const renamed = { ...org, name: name.trim() || null };

    updateThread(key, () => ({ target: { kind: 'org', org: renamed } }));
    await refreshOrg(renamed);
}

export async function addOrgMembers(
    org: OrgRef,
    members: string[],
    sharesHistory: boolean,
): Promise<void> {
    await request(OrgMemberController.store.url({ org: org.id }), 'POST', {
        members,
        share_history: sharesHistory,
    });
    await refreshOrg(org);
}

export async function setOrgMemberRole(
    org: OrgRef,
    member: User,
    role: OrgRole,
): Promise<void> {
    await request(
        OrgMemberController.update.url({
            org: org.id,
            member: member.username,
        }),
        'PUT',
        { role },
    );
    await refreshOrg(org);
}

/**
 * Removes a member, or leaves when the member is the user. Leaving takes the org out of the inbox.
 */
export async function removeOrgMember(
    org: OrgRef,
    member: User,
    isLeaving: boolean,
): Promise<void> {
    await request(
        OrgMemberController.destroy.url({
            org: org.id,
            member: member.username,
        }),
        'DELETE',
    );

    if (!isLeaving) {
        await refreshOrg(org);

        return;
    }

    const key = targetKey({ kind: 'org', org });
    const threads = { ...state.threads };
    delete threads[key];

    update({
        conversations: withoutKey(state.conversations, key),
        archived: withoutKey(state.archived, key),
        threads,
    });
}
