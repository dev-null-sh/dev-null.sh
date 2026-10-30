import { Link, usePage } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { login } from '@/routes';

/**
 * A link to a page only members can open, like who follows whom. Guests leave to sign in instead, and come back to the
 * page they were reading.
 */
export function MembersLink({
    href,
    children,
    className,
    title,
}: Pick<
    ComponentProps<typeof Link>,
    'href' | 'children' | 'className' | 'title'
>) {
    const { auth } = usePage().props;

    return auth.user === null ? (
        <a href={login().url} className={className} title={title}>
            {children}
        </a>
    ) : (
        <Link href={href} className={className} title={title}>
            {children}
        </Link>
    );
}
