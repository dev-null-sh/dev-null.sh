import { index } from '@/actions/App/Http/Controllers/GuildSuggestionController';
import type { GuildBadge } from '@/types/guild';

export async function fetchGuilds(
    query: { q?: string; handles?: string[] },
    signal?: AbortSignal,
): Promise<GuildBadge[]> {
    const response = await fetch(index.url({ query }), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        signal,
    });

    if (!response.ok) {
        throw new Error(`Guild lookup failed: ${response.status}`);
    }

    const data = (await response.json()) as { guilds: GuildBadge[] };

    return data.guilds;
}
