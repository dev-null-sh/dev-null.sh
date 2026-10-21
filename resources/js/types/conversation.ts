import type { User } from '@/types/auth';
import type { BodySegment, LinkPreview } from '@/types/commit';

/**
 * The message a reply answers, as much as its ghost shows.
 */
export type MessageReply = {
    id: number;
    user_id: string;
    body: string;
    images_count: number;
};

export type MessageImage = {
    id: string;
    url: string;
    width: number;
    height: number;
    description: string | null;
};

export type MessageReaction = {
    user_id: string;
    emoji: string;
};

/**
 * The reactions of a message that changed since the version a poll last saw.
 */
export type MessageReactionChange = {
    id: number;
    reactions: MessageReaction[];
};

export type Message = {
    id: number;
    user_id: string;
    client_id: string;
    body: string;
    body_segments?: BodySegment[];
    reply_to_id?: number | null;
    reply_to?: MessageReply | null;
    mentions?: Record<string, string> | null;
    link_preview?: LinkPreview | null;
    images?: MessageImage[];
    reactions?: MessageReaction[];
    event?: OrgEvent | null;
    subject_id?: string | null;
    user?: User;
    subject?: User | null;
    created_at: string;
};

export type ConversationKind = 'direct' | 'org';

export type OrgRole = 'maintainer' | 'contributor';

/**
 * What happened in an org, logged between its messages.
 */
export type OrgEvent =
    | 'created'
    | 'added'
    | 'removed'
    | 'left'
    | 'renamed'
    | 'promoted'
    | 'demoted'
    | 'inherited';

export type OrgRef = { id: string; name: string | null };

export type OrgMember = {
    user: User;
    role: OrgRole | null;
    read_through_id: number;
};

export type ConversationSummary = {
    id: string;
    kind: ConversationKind;
    name: string | null;
    partner: User | null;
    members: { user: User; role: OrgRole | null }[] | null;
    last_message: Message | null;
    last_message_at: string | null;
    ahead: number;
    partner_read_through_id: number;
    partner_read_at: string | null;
    is_archived: boolean;
    is_muted: boolean;
};

export type Thread = {
    messages: Message[];
    has_older: boolean;
    reactions_version: number;
    reaction_changes: MessageReactionChange[];
    read_through_id: number;
    partner_read_through_id: number;
    partner_read_at: string | null;
    partner_typing: boolean;
    is_archived: boolean;
    is_muted: boolean;
    access: PushAccess;
};

export type OrgThread = {
    messages: Message[];
    has_older: boolean;
    reactions_version: number;
    reaction_changes: MessageReactionChange[];
    read_through_id: number;
    members: OrgMember[];
    typing: string[];
    is_archived: boolean;
    is_muted: boolean;
    role: OrgRole | null;
    access: PushAccess;
};

/**
 * What the composer can do: push, merge a fetched request first, wait for the partner to merge, or nothing, either
 * because the partner doesn't follow the user while requests are off, or has remotes off.
 */
export type PushAccess =
    | 'open'
    | 'request'
    | 'waiting'
    | 'restricted'
    | 'closed';

export type InboxTab = 'open' | 'fetched' | 'stashed';

export type InboxPage = {
    conversations: ConversationSummary[];
    has_more: boolean;
};

export type RemotesFrom = 'following' | 'everyone';
