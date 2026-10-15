export type ActivityScale = 'day' | 'week' | 'month' | 'year';

export type ActivityBucket = {
    date: string;
    count: number;
};

export type Activity = {
    scale: ActivityScale;
    buckets: ActivityBucket[];
};
