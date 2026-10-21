import type { SponsorsFrom } from '@/types';

export const sponsorsFromChoices: SponsorsFrom[] = [
    'monthly',
    'active',
    'everyone',
];

export const sponsorsFromLabels: Record<SponsorsFrom, string> = {
    monthly: 'Monthly sponsors',
    active: 'Active sponsors',
    everyone: 'Everyone who sponsored',
};

export const sponsorsFromDescriptions: Record<SponsorsFrom, string> = {
    monthly: 'Only people sponsoring you every month.',
    active: 'Monthly sponsors, and one-time sponsors for 30 days after they pay.',
    everyone: 'Lapsed sponsors stay in, marked as lapsed for you.',
};
