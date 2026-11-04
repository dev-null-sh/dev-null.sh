export type UserList = {
    id: string;
    name: string;
    pinned_at: string | null;
    members_count?: number;
};

export type PinnedList = Pick<UserList, 'id' | 'name'>;

export type ProfileList = Pick<UserList, 'id' | 'name'> & {
    has_member: boolean;
};
