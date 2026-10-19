import { router, usePage } from '@inertiajs/react';
import { setCookie } from '@/hooks/use-appearance';

export function useNavOpen() {
    const { sidebarOpen } = usePage().props;

    const setNavOpen = (open: boolean): void => {
        setCookie('sidebar_state', String(open));
        router.flushAll();
        router.replaceProp('sidebarOpen', open);
    };

    return { isNavOpen: sidebarOpen, setNavOpen };
}
