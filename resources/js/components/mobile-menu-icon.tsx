import type { SVGAttributes } from 'react';

export function MobileMenuIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            aria-hidden
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            {...props}
        >
            <path d="M4 8h16M4 16h11" />
        </svg>
    );
}
