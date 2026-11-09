import { createContext, useContext } from 'react';

/**
 * Whether what renders sits in a commit embed, framed on another site. Its links then stay plain ones to /dev/null:
 * nothing prefetches or previews on hover, which would ask /dev/null for pages on every reader's hover.
 */
export const EmbeddedContext = createContext(false);

export function useIsEmbedded(): boolean {
    return useContext(EmbeddedContext);
}
