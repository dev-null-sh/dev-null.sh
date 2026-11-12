import type { SVGAttributes } from 'react';
import type { RemoteSound } from '@/lib/remote-notifications';

const shapes = {
    msn: (
        <>
            <circle cx="9" cy="7" r="3" />
            <path d="M3 20v-3a6 6 0 0 1 12 0v3Z" />
            <path d="M16 3a3 3 0 1 1 0 6m1 3a5 5 0 0 1 4 5v2h-3" />
        </>
    ),
    navi: (
        <>
            <path d="M8.4 11.3C3 10 1 5 3 3c2-2 6 2 7.5 6.8m5.1 1.5C21 10 23 5 21 3c-2-2-6 2-7.5 6.8" />
            <circle cx="12" cy="14" r="4.5" />
        </>
    ),
    icq: (
        <>
            {[0, 45, 90, 135, 180, 225, 270, 315].map((angle) => (
                <ellipse
                    key={angle}
                    cx="12"
                    cy="5.5"
                    rx="2"
                    ry="3"
                    transform={`rotate(${angle} 12 12)`}
                />
            ))}
            <circle cx="12" cy="12" r="2" />
        </>
    ),
    aim: (
        <>
            <circle cx="16" cy="4" r="2" />
            <path d="m3 9 5-3 6 3-3 6 5 2 1 5m-6-7-4 6H2m12-12 3 4h5" />
        </>
    ),
};

export function RemoteSoundIcon({
    sound,
    ...props
}: SVGAttributes<SVGElement> & { sound: RemoteSound }) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            {...props}
        >
            {shapes[sound]}
        </svg>
    );
}
