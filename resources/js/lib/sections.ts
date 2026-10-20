import type { LucideIcon } from 'lucide-react';
import { Briefcase, Layers } from 'lucide-react';

/**
 * The titles and slugs of the sections come from the server's `Section` enum, which only the icons are drawn here for.
 */
const icons: Record<string, LucideIcon> = {
    hiring: Briefcase,
};

export function sectionIcon(section: string): LucideIcon {
    return icons[section] ?? Layers;
}
