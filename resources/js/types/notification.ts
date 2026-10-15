export type CommitNotification = {
    id: string;
    read_at: string | null;
    created_at: string;
    data: {
        commit_id: string;
        body: string;
        user: {
            username: string;
            name: string;
            avatar_url: string | null;
        };
    };
};
