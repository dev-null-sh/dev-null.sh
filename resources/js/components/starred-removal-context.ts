import { createContext } from 'react';

export const StarredRemovalContext = createContext<
    ((id: string) => void) | null
>(null);
