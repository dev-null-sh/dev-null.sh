import { edit as editAppearance } from '@/routes/appearance';
import { index as devices } from '@/routes/device';
import { edit as editSafety } from '@/routes/safety';
import { show as showTerminal } from '@/routes/terminal';
import { edit as editProfile } from '@/routes/user-profile';

/**
 * The settings tabs, in the order they appear. The content under them slides in this order too.
 */
export const settingsTabs = [
    { title: 'Profile', href: editProfile() },
    { title: 'Appearance', href: editAppearance() },
    { title: 'Terminal', href: showTerminal() },
    { title: 'Safety', href: editSafety() },
    { title: 'Devices', href: devices() },
];
