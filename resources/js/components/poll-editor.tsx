import { useLang } from '@erag/lang-sync-inertia/react';
import { ChevronDown, Plus, X } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type PollDraft = {
    options: string[];
    duration: number;
};

export const emptyPollDraft: PollDraft = { options: ['', ''], duration: 1440 };

const maxOptionLength = 50;

const maxOptions = 4;

const durations = [
    { minutes: 60, unit: 'hours', count: 1 },
    { minutes: 1440, unit: 'days', count: 1 },
    { minutes: 4320, unit: 'days', count: 3 },
    { minutes: 10080, unit: 'days', count: 7 },
] as const;

export function PollEditor({
    id,
    poll,
    errors,
    onChange,
    onRemove,
}: {
    id: string;
    poll: PollDraft;
    errors: Record<string, string | undefined>;
    onChange: (poll: PollDraft) => void;
    onRemove: () => void;
}) {
    const { __, transChoice } = useLang();
    const updateOption = (index: number, value: string) =>
        onChange({
            ...poll,
            options: poll.options.map((option, current) =>
                current === index ? value : option,
            ),
        });

    const removeOption = (index: number) => {
        onChange({
            ...poll,
            options: poll.options.filter((_, current) => current !== index),
        });
        requestAnimationFrame(() =>
            document.getElementById(`${id}-option-${index - 1}`)?.focus(),
        );
    };

    const addOption = () => {
        onChange({ ...poll, options: [...poll.options, ''] });
        requestAnimationFrame(() =>
            document
                .getElementById(`${id}-option-${poll.options.length}`)
                ?.focus(),
        );
    };

    const pollError =
        errors['poll.options'] ?? errors['poll.duration'] ?? errors.poll;

    return (
        <fieldset
            className="mt-2 rounded-lg border p-3"
            data-test="poll-editor"
        >
            <legend className="sr-only">{__('composer.poll.legend')}</legend>

            <div className="space-y-2">
                {poll.options.map((option, index) => {
                    const error = errors[`poll.options.${index}`];

                    return (
                        <div key={index}>
                            <div className="flex items-center gap-2">
                                <label
                                    htmlFor={`${id}-option-${index}`}
                                    className="sr-only"
                                >
                                    {__('composer.poll.option', {
                                        number: index + 1,
                                    })}
                                </label>
                                <input
                                    id={`${id}-option-${index}`}
                                    name="poll[options][]"
                                    value={option}
                                    onChange={(event) =>
                                        updateOption(
                                            index,
                                            event.currentTarget.value,
                                        )
                                    }
                                    maxLength={maxOptionLength}
                                    placeholder={__('composer.poll.option', {
                                        number: index + 1,
                                    })}
                                    autoComplete="off"
                                    aria-invalid={error ? true : undefined}
                                    className={cn(
                                        'h-9 w-full min-w-0 rounded-md border bg-transparent px-3 text-[15px] transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                        'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
                                    )}
                                />
                                {index >= 2 && (
                                    <button
                                        type="button"
                                        onClick={() => removeOption(index)}
                                        aria-label={__(
                                            'composer.poll.remove_option',
                                            { number: index + 1 },
                                        )}
                                        title={__(
                                            'composer.poll.remove_option_title',
                                        )}
                                        className="shrink-0 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    >
                                        <X className="size-4" />
                                    </button>
                                )}
                            </div>
                            <InputError message={error} className="mt-1" />
                        </div>
                    );
                })}
            </div>

            {poll.options.length < maxOptions && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={addOption}
                    className="mt-2 -ml-2 text-primary hover:text-primary"
                    data-test="add-poll-option-button"
                >
                    <Plus />
                    {__('composer.poll.add_option')}
                </Button>
            )}

            <div className="mt-2 flex flex-wrap items-center justify-between gap-2 border-t pt-3">
                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                    {__('composer.poll.length')}
                    <span className="relative inline-flex">
                        <select
                            id="poll-duration"
                            name="poll[duration]"
                            value={poll.duration}
                            onChange={(event) =>
                                onChange({
                                    ...poll,
                                    duration: Number(event.currentTarget.value),
                                })
                            }
                            className="h-8 appearance-none rounded-md border bg-background pr-8 pl-2.5 text-sm text-foreground outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            {durations.map((duration) => (
                                <option
                                    key={duration.minutes}
                                    value={duration.minutes}
                                >
                                    {transChoice(
                                        `composer.poll.${duration.unit}`,
                                        duration.count,
                                    )}
                                </option>
                            ))}
                        </select>
                        <ChevronDown
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                    </span>
                </label>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onRemove}
                    className="text-muted-foreground hover:text-destructive"
                >
                    {__('composer.poll.remove')}
                </Button>
            </div>

            <InputError message={pollError} className="mt-2" />
        </fieldset>
    );
}
