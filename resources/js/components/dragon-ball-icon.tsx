import type { SVGAttributes } from 'react';

export function DragonBallIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden
            {...props}
        >
            <circle cx="12" cy="12" r="10" />
            <path d="M5.2 8.5a8 8 0 0 1 3.3-3.3" strokeWidth="1.2" />
            <path d="M4.5 11v.5" strokeWidth="1.2" />
            <path
                d="m12 9 1 2.1 2.3.3-1.65 1.6.4 2.3L12 14.2l-2.05 1.1.4-2.3-1.65-1.6 2.3-.3z"
                fill="currentColor"
                stroke="none"
            />
        </svg>
    );
}
