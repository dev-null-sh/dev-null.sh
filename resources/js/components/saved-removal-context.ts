import { createContext } from 'react';

export const SavedRemovalContext = createContext<((id: string) => void) | null>(
    null,
);
