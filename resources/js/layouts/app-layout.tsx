import { usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { AppAside } from '@/components/app-aside';
import { AppNav, MobileHeader, MobileNav } from '@/components/app-nav';
import { KeyboardShortcutsDialog } from '@/components/keyboard-shortcuts-dialog';
import { useKeyboardShortcuts } from '@/hooks/use-keyboard-shortcuts';
import type { AppLayoutProps } from '@/types';

export default function AppLayout({ children }: AppLayoutProps) {
    const { auth } = usePage().props;
    const [isShowingShortcuts, setIsShowingShortcuts] = useState(false);
    const showShortcuts = useCallback(() => setIsShowingShortcuts(true), []);

    useKeyboardShortcuts(auth.user, showShortcuts);

    return (
        <div className="mx-auto flex min-h-svh w-full max-w-[1264px] justify-center">
            <AppNav />

            <main className="min-w-0 flex-1 bg-card pb-20 md:max-w-[620px] md:border-x md:pb-0">
                <MobileHeader />
                {children}
            </main>

            <AppAside onShowShortcuts={showShortcuts} />

            <MobileNav />

            <KeyboardShortcutsDialog
                open={isShowingShortcuts}
                onOpenChange={setIsShowingShortcuts}
            />
        </div>
    );
}
