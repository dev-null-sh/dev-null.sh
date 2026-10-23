import type { CommitGif } from '@/types/commit';
import { giphy } from './giphy.ts';
import { klipy } from './klipy.ts';
import type {
    GifAttachment,
    GifProvider,
    GifProviderId,
    GifResult,
} from './types.ts';

export * from './types.ts';

export const gifProviders: Record<GifProviderId, GifProvider> = {
    klipy,
    giphy,
};

export function gifProviderFor(id: GifProviderId): GifProvider {
    return gifProviders[id];
}

export function attachmentFromResult(
    id: GifProviderId,
    result: GifResult,
): GifAttachment {
    return { ...result, provider: id };
}

/** A gif attachment shaped for CommitGif to render, dropping the draft's extra previewUrl field. */
export function toCommitGif(gif: GifAttachment): CommitGif {
    return {
        provider: gif.provider,
        id: gif.id,
        url: gif.url,
        page_url: gif.pageUrl,
        title: gif.title,
        username: gif.username,
        width: gif.width,
        height: gif.height,
    };
}

export function gifFormFields(
    gif: GifAttachment,
): Record<string, string | number> {
    return {
        gif_provider: gif.provider,
        gif_id: gif.id,
        gif_url: gif.url,
        gif_page_url: gif.pageUrl,
        gif_title: gif.title ?? '',
        gif_username: gif.username ?? '',
        gif_width: gif.width,
        gif_height: gif.height,
    };
}
