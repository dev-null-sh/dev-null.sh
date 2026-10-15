import { usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { AppAside } from '@/components/app-aside';
import { AppNav, MobileHeader, MobileNav } from '@/components/app-nav';
import { KeyboardShortcutsDialog } from '@/components/keyboard-shortcuts-dialog';
import { UserSearchDialog } from '@/components/user-search-dialog';
import { useKeyboardShortcuts } from '@/hooks/use-keyboard-shortcuts';
import type { AppLayoutProps } from '@/types';

export default function AppLayout({ children }: AppLayoutProps) {
    const { auth } = usePage().props;
    const [isShowingShortcuts, setIsShowingShortcuts] = useState(false);
    const [isSearching, setIsSearching] = useState(false);
    const showShortcuts = useCallback(() => setIsShowingShortcuts(true), []);
    const openSearch = useCallback(() => setIsSearching(true), []);

    useKeyboardShortcuts(auth.user, showShortcuts, openSearch);

    return (
        <div className="mx-auto flex min-h-svh w-full max-w-[1264px] justify-center">
            <AppNav onSearch={openSearch} />

            <main className="min-w-0 flex-1 bg-card pb-20 md:max-w-[620px] md:border-x md:pb-0">
                <MobileHeader onSearch={openSearch} />
                {children}
            </main>

            <AppAside onShowShortcuts={showShortcuts} onSearch={openSearch} />

            <MobileNav />

            <UserSearchDialog
                open={isSearching}
                onOpenChange={setIsSearching}
            />

            <KeyboardShortcutsDialog
                open={isShowingShortcuts}
                onOpenChange={setIsShowingShortcuts}
            />
        </div>
    );
}
