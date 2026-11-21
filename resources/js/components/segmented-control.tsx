import type { LucideIcon } from 'lucide-react';
import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';

export function SegmentedControl<T>({
    label,
    name,
    options,
    value,
    onChange,
    iconOnly = false,
    className,
}: {
    label: string;
    name: string;
    options: { value: T; icon?: LucideIcon; label: string }[];
    value: T;
    onChange: (value: T) => void;
    iconOnly?: boolean;
    className?: string;
}) {
    return (
        <div
            role="radiogroup"
            aria-label={label}
            className={cn('inline-flex gap-1 rounded-lg border p-1', className)}
        >
            {options.map((option) => (
                <button
                    key={option.label}
                    type="button"
                    role="radio"
                    aria-checked={option.value === value}
                    title={iconOnly ? option.label : undefined}
                    onClick={() => onChange(option.value)}
                    className={cn(
                        'relative isolate rounded-md px-3.5 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        {
                            'text-primary hover:bg-transparent hover:text-primary':
                                option.value === value,
                        },
                    )}
                >
                    {option.value === value && (
                        <span
                            aria-hidden
                            className="absolute inset-0 -z-10 rounded-md bg-primary-soft in-place:[view-transition-name:var(--transition-name)]"
                            style={
                                {
                                    '--transition-name': `${name}-active`,
                                } as CSSProperties
                            }
                        />
                    )}
                    <span
                        className="flex items-center gap-2 [view-transition-class:indicator-label] in-place:[view-transition-name:var(--transition-name)]"
                        style={
                            {
                                '--transition-name': `${name}-${option.label.toLowerCase()}`,
                            } as CSSProperties
                        }
                    >
                        {option.icon && <option.icon className="size-4" />}
                        {iconOnly ? (
                            <span className="sr-only">{option.label}</span>
                        ) : (
                            option.label
                        )}
                    </span>
                </button>
            ))}
        </div>
    );
}
