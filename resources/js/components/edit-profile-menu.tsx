import { router } from '@inertiajs/react';
import { ChevronDown, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import UserProfileController from '@/actions/App/Http/Controllers/UserProfileController';
import { GitHubIcon } from '@/components/github-icon';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';

export function EditProfileMenu() {
    const [isSyncing, setIsSyncing] = useState(false);

    const sync = () =>
        router.visit(UserProfileController.update(), {
            preserveScroll: true,
            onStart: () => setIsSyncing(true),
            onFinish: () => setIsSyncing(false),
            onSuccess: () => router.flushAll(),
        });

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    aria-busy={isSyncing}
                    data-test="edit-profile-menu"
                >
                    Edit profile
                    {isSyncing ? (
                        <Spinner className="size-3.5" />
                    ) : (
                        <ChevronDown className="size-3.5 opacity-60" />
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuItem asChild>
                    <a
                        href="https://github.com/settings/profile"
                        className="cursor-pointer"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <GitHubIcon />
                        Edit on GitHub
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem
                    disabled={isSyncing}
                    onSelect={sync}
                    data-test="sync-profile"
                >
                    <RefreshCw />
                    Sync from GitHub
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
