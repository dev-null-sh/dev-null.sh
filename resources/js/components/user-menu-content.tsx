import { Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    AppWindow,
    BookOpen,
    Star,
    FileText,
    LogOut,
    Settings,
    Shield,
    UserRound,
} from 'lucide-react';
import AboutController from '@/actions/App/Http/Controllers/AboutController';
import AppController from '@/actions/App/Http/Controllers/AppController';
import OperationsController from '@/actions/App/Http/Controllers/OperationsController';
import PrivacyController from '@/actions/App/Http/Controllers/PrivacyController';
import StarredCommitController from '@/actions/App/Http/Controllers/StarredCommitController';
import TermsController from '@/actions/App/Http/Controllers/TermsController';
import { GitHubIcon } from '@/components/github-icon';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { clearRecentSearches } from '@/hooks/use-recent-searches';
import { clearStashes } from '@/hooks/use-stash';
import { logout } from '@/routes';
import { show } from '@/routes/user';
import { edit } from '@/routes/user-profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const { auth, repository } = usePage().props;
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        clearStashes(user.id);
        clearRecentSearches(user.id);
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showUsername={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={StarredCommitController.index()}
                        onClick={cleanup}
                    >
                        <Star className="mr-2" />
                        Starred
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={show(user)}
                        prefetch
                        onClick={cleanup}
                    >
                        <UserRound className="mr-2" />
                        Profile
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
                {auth.isAdmin && (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={OperationsController.show()}
                            onClick={cleanup}
                        >
                            <Activity className="mr-2" />
                            Operations
                        </Link>
                    </DropdownMenuItem>
                )}
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={AboutController.show()}
                        onClick={cleanup}
                    >
                        <BookOpen className="mr-2" />
                        About
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={AppController.index()}
                        onClick={cleanup}
                    >
                        <AppWindow className="mr-2" />
                        Apps
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={TermsController.show()}
                        onClick={cleanup}
                    >
                        <FileText className="mr-2" />
                        Terms
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={PrivacyController.show()}
                        onClick={cleanup}
                    >
                        <Shield className="mr-2" />
                        Privacy
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={`https://github.com/${repository}`}
                        className="cursor-pointer"
                        target="_blank"
                        rel="noopener noreferrer"
                        onClick={cleanup}
                        data-test="source-code-link"
                    >
                        <GitHubIcon className="mr-2" />
                        GitHub
                    </a>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
