import { useForm } from '@inertiajs/react';
import { useId } from 'react';
import ModerationRuleController from '@/actions/App/Http/Controllers/ModerationRuleController';
import { thresholdSummary } from '@/components/evaluation-verdict';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type {
    ModerationRule,
    ModerationRuleAction,
    ModerationRuleTemplate,
    ModerationStrictness,
} from '@/types';

export type StrictnessPresets = Record<
    ModerationStrictness,
    Record<ModerationRuleAction, { act: number | null; review: number | null }>
>;

export const actionLabels: Record<ModerationRuleAction, string> = {
    reject: 'Reject',
    review: 'Flag for review',
    approve: 'Approve if it matches',
};

export const strictnessCopy: Record<ModerationStrictness, string> = {
    strict: 'Catches more. More posts land in your queue.',
    balanced: 'Acts only when clearly sure. Unsure posts go to you.',
    lenient: 'Acts only when almost certain.',
};

const textareaClassName =
    'block min-h-20 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 aria-invalid:border-destructive disabled:opacity-50';

const blank: ModerationRuleTemplate = {
    name: '',
    matches: '',
    does_not_match: '',
    action: 'reject',
    strictness: 'balanced',
    mode: 'test',
    rejection_message: '',
};

function TextField({
    label,
    hint,
    value,
    error,
    maxLength,
    multiline = false,
    onChange,
}: {
    label: string;
    hint?: string;
    value: string;
    error?: string;
    maxLength: number;
    multiline?: boolean;
    onChange: (value: string) => void;
}) {
    const id = useId();
    const describedBy = [hint && `${id}-hint`, error && `${id}-error`]
        .filter(Boolean)
        .join(' ');

    return (
        <div className="space-y-1.5">
            <Label htmlFor={id}>{label}</Label>
            {multiline ? (
                <textarea
                    id={id}
                    value={value}
                    maxLength={maxLength}
                    onChange={(event) => onChange(event.target.value)}
                    aria-invalid={Boolean(error)}
                    aria-describedby={describedBy || undefined}
                    className={textareaClassName}
                />
            ) : (
                <Input
                    id={id}
                    value={value}
                    maxLength={maxLength}
                    onChange={(event) => onChange(event.target.value)}
                    aria-invalid={Boolean(error)}
                    aria-describedby={describedBy || undefined}
                />
            )}
            {hint && (
                <p id={`${id}-hint`} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}

function RuleForm({
    rule,
    templates,
    presets,
    onSaved,
}: {
    rule: ModerationRule | null;
    templates: ModerationRuleTemplate[];
    presets: StrictnessPresets;
    onSaved: () => void;
}) {
    const form = useForm<ModerationRuleTemplate>(
        rule
            ? {
                  name: rule.name,
                  matches: rule.matches,
                  does_not_match: rule.does_not_match ?? '',
                  action: rule.action,
                  strictness: rule.strictness,
                  mode: rule.mode,
                  rejection_message: rule.rejection_message ?? '',
              }
            : blank,
    );
    const thresholds = presets[form.data.strictness][form.data.action];

    const save = () => {
        form.transform((data) => ({
            ...data,
            does_not_match: data.does_not_match || null,
            rejection_message:
                data.action === 'reject'
                    ? data.rejection_message || null
                    : null,
        }));

        form.submit(
            rule
                ? ModerationRuleController.update(rule)
                : ModerationRuleController.store(),
            { preserveScroll: true, onSuccess: onSaved },
        );
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                save();
            }}
            className="space-y-5"
        >
            {!rule && (
                <fieldset>
                    <legend className="mb-2 text-sm font-medium">
                        Start from
                    </legend>
                    <div className="flex flex-wrap gap-1.5">
                        {[blank, ...templates].map((template) => (
                            <button
                                key={template.name || 'blank'}
                                type="button"
                                onClick={() =>
                                    form.setData({
                                        ...template,
                                        does_not_match:
                                            template.does_not_match ?? '',
                                        rejection_message:
                                            template.rejection_message ?? '',
                                        mode: 'test',
                                    })
                                }
                                aria-pressed={form.data.name === template.name}
                                className={cn(
                                    'rounded-full border px-2.5 py-1 text-xs transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    {
                                        'border-primary bg-primary-soft text-primary hover:bg-primary-soft':
                                            form.data.name === template.name,
                                    },
                                )}
                                data-test="rule-template"
                            >
                                {template.name || 'Blank'}
                            </button>
                        ))}
                    </div>
                </fieldset>
            )}

            <TextField
                label="Name"
                value={form.data.name}
                error={form.errors.name}
                maxLength={80}
                onChange={(value) => form.setData('name', value)}
            />
            <TextField
                label="What counts"
                hint="Write what counts as a match, like you'd explain it to a new moderator."
                value={form.data.matches}
                error={form.errors.matches}
                maxLength={500}
                multiline
                onChange={(value) => form.setData('matches', value)}
            />
            <TextField
                label="What doesn't count"
                hint="Say what doesn't count. This prevents false alarms."
                value={form.data.does_not_match ?? ''}
                error={form.errors.does_not_match}
                maxLength={500}
                multiline
                onChange={(value) => form.setData('does_not_match', value)}
            />

            <div className="space-y-1.5">
                <p className="text-sm font-medium" id="rule-action-label">
                    When it matches
                </p>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    value={form.data.action}
                    onValueChange={(value: ModerationRuleAction | '') =>
                        value && form.setData('action', value)
                    }
                    aria-labelledby="rule-action-label"
                    className="flex-wrap"
                >
                    {(['reject', 'review', 'approve'] as const).map(
                        (action) => (
                            <ToggleGroupItem
                                key={action}
                                value={action}
                                className="px-3"
                            >
                                {actionLabels[action]}
                            </ToggleGroupItem>
                        ),
                    )}
                </ToggleGroup>
                <InputError message={form.errors.action} />
            </div>

            <div className="space-y-1.5">
                <p className="text-sm font-medium" id="rule-strictness-label">
                    Strictness
                </p>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    value={form.data.strictness}
                    onValueChange={(value: ModerationStrictness | '') =>
                        value && form.setData('strictness', value)
                    }
                    aria-labelledby="rule-strictness-label"
                >
                    {(['strict', 'balanced', 'lenient'] as const).map(
                        (strictness) => (
                            <ToggleGroupItem
                                key={strictness}
                                value={strictness}
                                className="px-3 capitalize"
                            >
                                {strictness}
                            </ToggleGroupItem>
                        ),
                    )}
                </ToggleGroup>
                <p className="text-xs text-muted-foreground">
                    {strictnessCopy[form.data.strictness]}{' '}
                    {thresholdSummary(
                        form.data.action,
                        thresholds.act,
                        thresholds.review,
                    )}
                    .
                </p>
            </div>

            {form.data.action === 'reject' && (
                <TextField
                    label="Message to the author"
                    hint="Shown with the rejection. Only the author sees it."
                    value={form.data.rejection_message ?? ''}
                    error={form.errors.rejection_message}
                    maxLength={280}
                    onChange={(value) =>
                        form.setData('rejection_message', value)
                    }
                />
            )}

            {!rule && (
                <p className="text-xs text-muted-foreground">
                    New rules start in Test mode: they record what they would do
                    without acting.
                </p>
            )}

            <DialogFooter>
                <Button
                    type="submit"
                    disabled={form.processing}
                    data-test="save-rule-button"
                >
                    {rule ? 'Save rule' : 'Add rule'}
                </Button>
            </DialogFooter>
        </form>
    );
}

export function ModerationRuleEditor({
    rule,
    open,
    onOpenChange,
    templates,
    presets,
}: {
    rule: ModerationRule | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    templates: ModerationRuleTemplate[];
    presets: StrictnessPresets;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>{rule ? 'Edit rule' : 'Add a rule'}</DialogTitle>
                <DialogDescription>
                    Jev is asked whether each new commit matches this rule.
                    Describe it plainly; the question around it is fixed.
                </DialogDescription>
                <RuleForm
                    key={rule?.id ?? 'new'}
                    rule={rule}
                    templates={templates}
                    presets={presets}
                    onSaved={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
