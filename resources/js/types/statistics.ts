import type { ActivityBucket } from './activity';

export type Statistics = {
    users: number;
    commits: number;
    activity: ActivityBucket[];
    updatedAt: string;
};
