import { useEffect, useRef, useState } from 'react';
import { formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';

const ANIMATION_MS = 250;

interface OdometerCountProps {
    value: number;
    className?: string;
    emptyWhenZero?: boolean;
}

function displayedCount(value: number, emptyWhenZero: boolean): string {
    return emptyWhenZero && value === 0 ? '' : formatCount(value);
}

export function OdometerCount({
    value,
    className,
    emptyWhenZero = false,
}: OdometerCountProps) {
    const previousValueRef = useRef(value);
    const [previous, setPrevious] = useState(() =>
        displayedCount(value, emptyWhenZero),
    );
    const [isAnimating, setIsAnimating] = useState(false);
    const [direction, setDirection] = useState<'up' | 'down'>('up');

    useEffect(() => {
        if (value === previousValueRef.current) {
            return;
        }

        const oldValue = previousValueRef.current;
        previousValueRef.current = value;
        setPrevious(displayedCount(oldValue, emptyWhenZero));
        setDirection(value >= oldValue ? 'up' : 'down');
        setIsAnimating(true);

        const timer = setTimeout(() => setIsAnimating(false), ANIMATION_MS);

        return () => clearTimeout(timer);
    }, [value, emptyWhenZero]);

    const current = displayedCount(value, emptyWhenZero);
    const length = Math.max(current.length, previous.length);
    const currentChars = current.padStart(length, ' ');
    const previousChars = previous.padStart(length, ' ');

    return (
        <span
            data-test="star-count"
            className={cn(
                'inline-flex min-w-[1ch] items-center text-left tabular-nums empty:hidden',
                className,
            )}
        >
            {isAnimating
                ? Array.from({ length }, (_, index) => {
                      const char = currentChars[index];
                      const previousChar = previousChars[index];
                      const place = length - index;

                      if (char === previousChar) {
                          return (
                              <span key={place} className="inline-block">
                                  {char}
                              </span>
                          );
                      }

                      return (
                          <span
                              key={`${place}-${char}`}
                              className="relative inline-flex h-[1.25em] overflow-hidden leading-[1.25em]"
                          >
                              {previousChar !== ' ' && (
                                  <span
                                      aria-hidden="true"
                                      className={cn(
                                          'absolute inset-0 flex items-center justify-center',
                                          direction === 'up'
                                              ? 'motion-safe:animate-odometer-up-out'
                                              : 'motion-safe:animate-odometer-down-out',
                                      )}
                                  >
                                      {previousChar}
                                  </span>
                              )}
                              {char !== ' ' ? (
                                  <span
                                      className={cn(
                                          'flex items-center justify-center',
                                          direction === 'up'
                                              ? 'motion-safe:animate-odometer-up-in'
                                              : 'motion-safe:animate-odometer-down-in',
                                      )}
                                  >
                                      {char}
                                  </span>
                              ) : (
                                  <span className="invisible inline-block">
                                      {previousChar}
                                  </span>
                              )}
                          </span>
                      );
                  })
                : current}
        </span>
    );
}
