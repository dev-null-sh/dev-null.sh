export type Device = {
    id: string;
    name: string;
    app_name: string | null;
    fingerprint: string;
    approved_at: string;
    last_used_at: string | null;
};
