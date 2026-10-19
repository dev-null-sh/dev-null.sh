import { usePage, usePoll } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { AppAside } from '@/components/app-aside';
import {
    AppNav,
    MobileCommitButton,
    MobileHeader,
    MobileNav,
} from '@/components/app-nav';
import { ChatDock } from '@/components/chat/chat-dock';
import {
    CommitComposerDialog,
    focusVisibleComposer,
} from '@/components/commit-composer-dialog';
import { CommitImageViewer } from '@/components/commit-image-viewer';
import { CommitStarPolling } from '@/components/commit-star-polling';
import { KeyboardShortcutsDialog } from '@/components/keyboard-shortcuts-dialog';
import { LocalSignInDialog } from '@/components/local-sign-in-dialog';
import { SearchDialog } from '@/components/search-dialog';
import { useIsComposerInView } from '@/hooks/use-composer-visibility';
import { useFixedPage } from '@/hooks/use-fixed-page';
import { useKeyboardShortcuts } from '@/hooks/use-keyboard-shortcuts';
import { useNavOpen } from '@/hooks/use-nav-open';
import { useTabSlide } from '@/hooks/use-tab-slide';
import { signIn } from '@/lib/auth';
import { cn } from '@/lib/utils';
import type { AppLayoutProps } from '@/types';

export default function AppLayout({ children }: AppLayoutProps) {
    const {
        component,
        props: { auth },
    } = usePage();
    const { isLayoutOpen } = useNavOpen();
    const [isShowingShortcuts, setIsShowingShortcuts] = useState(false);
    const [isSearching, setIsSearching] = useState(false);
    const [isComposing, setIsComposing] = useState(false);
    const showShortcuts = useCallback(() => setIsShowingShortcuts(true), []);
    const openSearch = useCallback(() => setIsSearching(true), []);
    const isGuest = auth.user === null;
    const isMessages = component.startsWith('conversation/');
    const hasComposer = component === 'log/show' && !isGuest;
    const isComposerInView = useIsComposerInView();
    const compose = useCallback(() => {
        if (isGuest) {
            signIn();

            return;
        }

        if (!focusVisibleComposer()) {
            setIsComposing(true);
        }
    }, [isGuest]);

    useKeyboardShortcuts(
        auth.user,
        auth.log,
        showShortcuts,
        openSearch,
        compose,
    );
    useTabSlide();
    useFixedPage(isMessages);

    return (
        <div
            data-nav={isLayoutOpen ? 'expanded' : 'collapsed'}
            className="mx-auto flex min-h-svh w-full max-w-[1264px] justify-center"
        >
            {component !== 'notification/index' && <NotificationCountPolling />}
            <div
                data-nav-rail
                className="sticky top-0 hidden h-svh w-20 shrink-0 md:block lg:w-(--sidebar-width)"
            >
                <AppNav onSearch={openSearch} onCompose={compose} />
            </div>

            <main
                data-main
                className={cn(
                    'min-w-0 flex-1 bg-card pb-40 md:max-w-[620px] md:border-x md:pb-0 lg:max-w-[calc(876px-var(--sidebar-width))]',
                    {
                        'pb-20 md:max-w-[940px] lg:max-w-[calc(1196px-var(--sidebar-width))]':
                            isMessages,
                    },
                )}
            >
                <div data-nav-content>
                    <MobileHeader onSearch={openSearch} />
                    <CommitStarPolling>{children}</CommitStarPolling>
                </div>
            </main>

            {!isMessages && (
                <AppAside
                    onShowShortcuts={showShortcuts}
                    onSearch={openSearch}
                />
            )}

            <MobileNav>
                {!isMessages && (
                    <MobileCommitButton
                        isHidden={hasComposer && isComposerInView}
                        onCompose={compose}
                    />
                )}
            </MobileNav>

            <CommitImageViewer />

            {auth.user !== null && auth.remotes !== null && !isMessages && (
                <ChatDock user={auth.user} />
            )}

            <SearchDialog open={isSearching} onOpenChange={setIsSearching} />

            <CommitComposerDialog
                open={isComposing}
                onOpenChange={setIsComposing}
            />

            <KeyboardShortcutsDialog
                open={isShowingShortcuts}
                onOpenChange={setIsShowingShortcuts}
                canModerate={auth.isAdmin}
            />

            {isGuest && <LocalSignInDialog />}
        </div>
    );
}

function NotificationCountPolling() {
    usePoll(
        30_000,
        { only: ['auth.unreadNotificationsCount', 'auth.unreadMessagesCount'] },
        { mode: 'rest', background: 'pause' },
    );

    return null;
}
