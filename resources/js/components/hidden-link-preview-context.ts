import { createContext } from 'react';

/**
 * The link whose card commits leave out, where the page already shows it above them.
 */
export const HiddenLinkPreviewContext = createContext<string | null>(null);
