import { useEffect } from 'react';
import { ImagePeek } from '@/lib/image-peek';

/**
 * Lets any image marked for it be peeked at by pressing and holding it. Mounted once, in the layout.
 */
export function ImagePeekHost() {
    useEffect(() => {
        const controller = new AbortController();

        new ImagePeek(controller.signal);

        return () => controller.abort();
    }, []);

    return null;
}
