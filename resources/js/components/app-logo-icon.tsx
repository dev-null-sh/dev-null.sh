import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="1.6 1.5 45 45"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path d="M2 42h10L26 6H16Z" />
            <path
                className="fill-(--lane-0)"
                fillRule="evenodd"
                clipRule="evenodd"
                d="M17.81 43.5h13.22L46.19 4.5H32.97ZM22.19 40.5l12.84-33h6.78l-12.84 33Z"
            />
        </svg>
    );
}
