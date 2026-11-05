// R is red, W is white. One character is one pixel.
const PIXELS = [
    '........WW',
    '......RRWW',
    '....RRRRR.',
    '..RRRRRRR.',
    '.RRRRRRRR.',
    'WWWWWWWWWW',
    'WWWWWWWWWW',
];

const COLORS: Record<string, string> = { R: '#d63031', W: '#f8f9fa' };

export default function SantaHat({ className }: { className?: string }) {
    return (
        <svg
            aria-hidden
            viewBox="0 0 10 7"
            shapeRendering="crispEdges"
            className={className}
        >
            {PIXELS.flatMap((row, y) =>
                row
                    .split('')
                    .map((pixel, x) => (
                        <rect
                            key={`${x}-${y}`}
                            x={x}
                            y={y}
                            width="1"
                            height="1"
                            fill={COLORS[pixel]}
                        />
                    )),
            )}
        </svg>
    );
}
