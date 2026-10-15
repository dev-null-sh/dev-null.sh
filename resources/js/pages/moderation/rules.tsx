import { Form, Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ModerationRuleController from '@/actions/App/Http/Controllers/ModerationRuleController';
import ModerationRulePositionController from '@/actions/App/Http/Controllers/ModerationRulePositionController';
import StarterModerationRuleController from '@/actions/App/Http/Controllers/StarterModerationRuleController';
import { EmptyFeed } from '@/components/commit-feed';
import {
    outcomeStyles,
    thresholdSummary,
} from '@/components/evaluation-verdict';
import { ModerationHeader } from '@/components/moderation-header';
import {
    ModerationRuleEditor,
    actionLabels,
    strictnessCopy,
} from '@/components/moderation-rule-editor';
import type { StrictnessPresets } from '@/components/moderation-rule-editor';
import { TryModerationRules } from '@/components/try-moderation-rules';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type {
    AutoModerationStats,
    ModerationRule,
    ModerationRuleMode,
    ModerationRuleTemplate,
    ModerationStrictness,
    RuleOutcome,
} from '@/types';

type Status = { isConfigured: boolean; isEnabled: boolean; model: string };

const actionOutcomes: Record<ModerationRule['action'], RuleOutcome> = {
    reject: 'reject',
    review: 'review',
    approve: 'approve',
};

function StatusLine({ status }: { status: Status }) {
    if (!status.isConfigured) {
        return <span>AI moderation: no API key</span>;
    }

    return (
        <span className="flex items-center gap-1.5">
            <span
                aria-hidden
                className={cn(
                    'size-2 rounded-full',
                    status.isEnabled
                        ? 'bg-emerald-500'
                        : 'bg-muted-foreground/50',
                )}
            />
            {status.isEnabled
                ? 'AI moderation on'
                : 'AI moderation off by configuration'}
            <span className="hidden font-mono text-xs sm:inline">
                · {status.model}
            </span>
        </span>
    );
}

function Stats({ stats }: { stats: AutoModerationStats }) {
    const tiles = [
        { label: 'Approved', value: stats.approved },
        { label: 'Rejected', value: stats.rejected },
        { label: 'To review', value: stats.review },
        { label: 'Failed', value: stats.failed },
        { label: 'Overturned', value: stats.overturned },
    ];

    return (
        <section
            className="border-b px-4 py-5 sm:px-6"
            aria-label="Last 7 days"
        >
            <p className="mb-3 font-mono text-xs text-muted-foreground">
                jev decisions, last 7 days
            </p>
            <dl className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                {tiles.map((tile) => (
                    <div
                        key={tile.label}
                        className="rounded-lg border px-3 py-2"
                    >
                        <dt className="truncate text-xs text-muted-foreground">
                            {tile.label}
                        </dt>
                        <dd className="text-xl font-semibold tabular-nums">
                            {tile.value}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

function RuleCard({
    rule,
    presets,
    isFirst,
    isLast,
    onEdit,
    onDelete,
}: {
    rule: ModerationRule;
    presets: StrictnessPresets;
    isFirst: boolean;
    isLast: boolean;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const thresholds = presets[rule.strictness][rule.action];
    const update = (data: Partial<ModerationRule>) =>
        router.patch(ModerationRuleController.update(rule), data, {
            preserveScroll: true,
            preserveState: true,
        });
    const move = (direction: 'up' | 'down') =>
        router.put(
            ModerationRulePositionController.update(rule),
            { direction },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <li className="rounded-lg border p-4" data-test="moderation-rule">
            <div className="flex items-start gap-2">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-semibold">{rule.name}</h3>
                        <span
                            className={cn(
                                'rounded-full border px-2 py-0.5 font-mono text-xs',
                                outcomeStyles[actionOutcomes[rule.action]],
                            )}
                        >
                            {actionLabels[rule.action]}
                        </span>
                    </div>
                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                        {rule.matches}
                    </p>
                </div>
                <div className="flex shrink-0 items-center">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        disabled={isFirst}
                        onClick={() => move('up')}
                        aria-label={`Move ${rule.name} up`}
                    >
                        <ArrowUp />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        disabled={isLast}
                        onClick={() => move('down')}
                        aria-label={`Move ${rule.name} down`}
                    >
                        <ArrowDown />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={onEdit}
                        aria-label={`Edit ${rule.name}`}
                        data-test="edit-rule-button"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 text-muted-foreground hover:text-destructive"
                        onClick={onDelete}
                        aria-label={`Delete ${rule.name}`}
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    value={rule.mode}
                    onValueChange={(mode: ModerationRuleMode | '') =>
                        mode && update({ mode })
                    }
                    aria-label={`${rule.name} mode`}
                    className="w-fit"
                    data-test="rule-mode"
                >
                    {(['off', 'test', 'live'] as const).map((mode) => (
                        <ToggleGroupItem
                            key={mode}
                            value={mode}
                            className={cn('px-3 capitalize', {
                                'data-[state=on]:bg-primary-soft data-[state=on]:text-primary':
                                    mode === 'live',
                            })}
                        >
                            {mode}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    value={rule.strictness}
                    onValueChange={(strictness: ModerationStrictness | '') =>
                        strictness && update({ strictness })
                    }
                    aria-label={`${rule.name} strictness`}
                    className="w-fit"
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
            </div>
            <p className="mt-2 text-xs text-muted-foreground">
                {thresholdSummary(
                    rule.action,
                    thresholds.act,
                    thresholds.review,
                )}
                . {strictnessCopy[rule.strictness]}
                {rule.mode === 'test' && ' Test mode records without acting.'}
            </p>
        </li>
    );
}

export default function Rules({
    rules,
    templates,
    presets,
    maxRules,
    status,
    stats,
}: {
    rules: ModerationRule[];
    templates: ModerationRuleTemplate[];
    presets: StrictnessPresets;
    maxRules: number;
    status: Status;
    stats: AutoModerationStats;
}) {
    const [editing, setEditing] = useState<ModerationRule | null>(null);
    const [isEditorOpen, setIsEditorOpen] = useState(false);
    const [deleting, setDeleting] = useState<ModerationRule | null>(null);

    const openEditor = (rule: ModerationRule | null) => {
        setEditing(rule);
        setIsEditorOpen(true);
    };

    return (
        <>
            <Head title="Moderation rules" />

            <ModerationHeader
                active="rules"
                aside={<StatusLine status={status} />}
            />

            <Stats stats={stats} />

            <section className="border-b px-4 py-6 sm:px-6">
                <div className="mb-4 flex items-start gap-3">
                    <div className="min-w-0 flex-1">
                        <h2 className="text-lg font-semibold tracking-tight">
                            Rules
                        </h2>
                        <p className="mt-1 max-w-prose text-sm text-pretty text-muted-foreground">
                            Jev checks every new commit against the Test and
                            Live rules in one request. Live rules act; Test
                            rules only record what they would have done. Unsure
                            commits always wait for you.
                        </p>
                    </div>
                    {rules.length > 0 && (
                        <Button
                            size="sm"
                            onClick={() => openEditor(null)}
                            disabled={rules.length >= maxRules}
                            data-test="add-rule-button"
                        >
                            <Plus />
                            Add rule
                        </Button>
                    )}
                </div>

                {rules.length === 0 ? (
                    <div
                        className="rounded-lg border border-dashed px-6 py-10 text-center"
                        data-test="rules-empty"
                    >
                        <EmptyFeed command="no rules configured">
                            <p>
                                Start with eight rules tested against spam,
                                scams, harassment and prompt injection. They
                                begin in Test mode, so nothing is moderated
                                until you switch one to Live.
                            </p>
                            <div className="mt-5 flex flex-wrap justify-center gap-2">
                                <Form
                                    {...StarterModerationRuleController.store.form()}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-test="add-starter-rules-button"
                                        >
                                            Add starter rules
                                        </Button>
                                    )}
                                </Form>
                                <Button
                                    variant="outline"
                                    onClick={() => openEditor(null)}
                                >
                                    Write your own
                                </Button>
                            </div>
                        </EmptyFeed>
                    </div>
                ) : (
                    <ol className="space-y-3">
                        {rules.map((rule, index) => (
                            <RuleCard
                                key={rule.id}
                                rule={rule}
                                presets={presets}
                                isFirst={index === 0}
                                isLast={index === rules.length - 1}
                                onEdit={() => openEditor(rule)}
                                onDelete={() => setDeleting(rule)}
                            />
                        ))}
                    </ol>
                )}

                {rules.length > 0 && (
                    <p className="mt-3 font-mono text-xs text-muted-foreground">
                        {rules.length} of {maxRules} rules
                    </p>
                )}
            </section>

            {rules.length > 0 && <TryModerationRules />}

            <ModerationRuleEditor
                rule={editing}
                open={isEditorOpen}
                onOpenChange={setIsEditorOpen}
                templates={templates}
                presets={presets}
            />

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete {deleting?.name}?</DialogTitle>
                    <DialogDescription>
                        New commits won't be checked against it. Past
                        evaluations keep their results.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">Keep it</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                deleting &&
                                router.delete(
                                    ModerationRuleController.destroy(deleting),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setDeleting(null),
                                    },
                                )
                            }
                        >
                            Delete rule
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Rules.layout = AppLayout;
