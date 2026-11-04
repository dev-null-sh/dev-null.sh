import type { SVGAttributes } from 'react';

export function MushroomCloudIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
            role="img"
            aria-label="Mushroom cloud"
            {...props}
        >
            <path d="M7 12H5a3 3 0 0 1-.4-6A3.5 3.5 0 0 1 9 3.5a4 4 0 0 1 6 0A3.5 3.5 0 0 1 19.4 6a3 3 0 0 1-.4 6h-2" />
            <path d="M7 9.5c0 1.5 2 2.5 5 2.5s5-1 5-2.5M9.5 12c.8 3 .4 5-2 7M14.5 12c-.8 3-.4 5 2 7" />
            <path d="M3 21c0-1.1 1.3-2 3-2h2m8 0h2c1.7 0 3 .9 3 2M3 21h18" />
        </svg>
    );
}
