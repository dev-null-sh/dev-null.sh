import { router } from '@inertiajs/react';
import { Copy, Image, Palette } from 'lucide-react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import BrandController from '@/actions/App/Http/Controllers/BrandController';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import { useAppearance } from '@/hooks/use-appearance';
import { useClipboard } from '@/hooks/use-clipboard';

/**
 * The menu behind a right-click on the logo, like most brand sites: copy the
 * logo, or go to the brand assets and the wallpapers.
 */
export function LogoMenu({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();
    const [, copy] = useClipboard();

    const copyLogo = async () => {
        const response = await fetch(
            `/images/brand/dev-null-logo-${resolvedAppearance}.svg`,
        );

        if (response.ok && (await copy(await response.text()))) {
            toast(
                <span className="font-mono text-[13px]">
                    Copied the logo as SVG
                </span>,
            );
        }
    };

    return (
        <ContextMenu>
            <ContextMenuTrigger asChild>{children}</ContextMenuTrigger>
            <ContextMenuContent className="w-48">
                <ContextMenuItem onSelect={copyLogo}>
                    <Copy />
                    Copy logo as SVG
                </ContextMenuItem>
                <ContextMenuSeparator />
                <ContextMenuItem
                    onSelect={() => router.visit(BrandController.show())}
                >
                    <Palette />
                    Brand assets
                </ContextMenuItem>
                <ContextMenuItem
                    onSelect={() =>
                        router.visit(`${BrandController.show().url}#wallpapers`)
                    }
                >
                    <Image />
                    Wallpapers
                </ContextMenuItem>
            </ContextMenuContent>
        </ContextMenu>
    );
}
