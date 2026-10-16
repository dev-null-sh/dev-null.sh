import { useEffect, useRef } from 'react';

/** Respect reduced motion (including changes), and avoid playing offscreen galleries. */
export function AnimationVideo({
    src,
    poster,
    label,
    className,
    width,
    height,
}: {
    src: string;
    poster?: string | null;
    label: string;
    className?: string;
    width?: number;
    height?: number;
}) {
    const ref = useRef<HTMLVideoElement>(null);
    useEffect(() => {
        const video = ref.current!;
        const motion = matchMedia('(prefers-reduced-motion: reduce)');
        let visible = false;
        const update = () => {
            if (motion.matches || !visible) video.pause();
            else void video.play().catch(() => {});
        };
        const observer = new IntersectionObserver(([entry]) => {
            visible = entry.isIntersecting;
            update();
        });
        observer.observe(video);
        motion.addEventListener('change', update);
        return () => {
            observer.disconnect();
            motion.removeEventListener('change', update);
            video.pause();
        };
    }, [src]);
    return (
        <video
            ref={ref}
            src={src}
            poster={poster ?? undefined}
            aria-label={label}
            width={width}
            height={height}
            muted
            loop
            playsInline
            controls
            preload="metadata"
            className={className}
            onClick={(event) => event.stopPropagation()}
            onPointerDown={(event) => event.stopPropagation()}
        />
    );
}
