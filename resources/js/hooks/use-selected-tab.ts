import { useState } from 'react';

/**
 * The tab to show as selected: the one just tapped while its visit loads, then whichever tab the page lands on,
 * including after back and forward.
 */
export function useSelectedTab<Tab>(tab: Tab): [Tab, (tab: Tab) => void] {
    const [selectedTab, setSelectedTab] = useState(tab);
    const [loadedTab, setLoadedTab] = useState(tab);

    if (tab !== loadedTab) {
        setLoadedTab(tab);
        setSelectedTab(tab);
    }

    return [selectedTab, setSelectedTab];
}
