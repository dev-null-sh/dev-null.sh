import { Head, router, useForm, useHttp } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Bold,
    Code,
    Heading2,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Send,
    Sparkles,
} from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { toast } from 'sonner';
import EmailController from '@/actions/App/Http/Controllers/EmailController';
import EmailDraftController from '@/actions/App/Http/Controllers/EmailDraftController';
import EmailPreviewController from '@/actions/App/Http/Controllers/EmailPreviewController';
import TestEmailController from '@/actions/App/Http/Controllers/TestEmailController';
import InputError from '@/components/input-error';
import { Panel } from '@/components/operations/section';
import { SegmentedControl } from '@/components/segmented-control';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import OperationsLayout from '@/layouts/operations/layout';
import { hasModKey, useKeyLabels } from '@/lib/keyboard';
import { formatMarkdown } from '@/lib/markdown-format';
import type { MarkdownFormat } from '@/lib/markdown-format';
import { formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';

const ACTIVITIES: Record<string, string> = {
    anyone: 'Anyone',
    'active-7d': 'Seen in the last 7 days',
    'active-30d': 'Seen in the last 30 days',
    'active-90d': 'Seen in the last 90 days',
    'inactive-30d': 'Not seen for 30 days',
    'inactive-90d': 'Not seen for 90 days',
};

const SIGNUPS: Record<string, string> = {
    anytime: 'Anytime',
    '7d': 'In the last 7 days',
    '30d': 'In the last 30 days',
    '90d': 'In the last 90 days',
};

const DRAFTS: Record<string, string> = {
    weekly: 'Weekly update',
    monthly: 'Monthly update',
};

const TOOLS: {
    format: MarkdownFormat;
    label: string;
    icon: LucideIcon;
    key?: string;
}[] = [
    { format: 'bold', label: 'Bold', icon: Bold, key: 'b' },
    { format: 'italic', label: 'Italic', icon: Italic, key: 'i' },
    { format: 'link', label: 'Link', icon: Link2, key: 'k' },
    { format: 'code', label: 'Code', icon: Code },
    { format: 'heading', label: 'Heading', icon: Heading2 },
    { format: 'bulleted', label: 'Bulleted list', icon: List },
    { format: 'numbered', label: 'Numbered list', icon: ListOrdered },
    { format: 'quote', label: 'Quote', icon: Quote },
];

const inputClassName =
    'h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 md:text-sm';

/**
 * Markdown, written with a toolbar and its shortcuts, and previewed as the email it is sent as.
 */
function Editor({
    value,
    onChange,
    subject,
    error,
    disabled,
}: {
    value: string;
    onChange: (value: string) => void;
    subject: string;
    error?: string;
    disabled: boolean;
}) {
    const textarea = useRef<HTMLTextAreaElement>(null);
    const [mode, setMode] = useState<'write' | 'preview'>('write');
    const [preview, setPreview] = useState<{
        key: string;
        html: string;
    } | null>(null);
    const { combo } = useKeyLabels();
    const request = useHttp<
        { subject: string; body: string },
        { html: string }
    >({ subject: '', body: '' });
    const previewKey = `${subject}\n${value}`;

    const apply = (format: MarkdownFormat) => {
        const element = textarea.current;

        if (!element) {
            return;
        }

        const edit = formatMarkdown(
            element.value,
            element.selectionStart,
            element.selectionEnd,
            format,
        );

        element.focus();
        element.setSelectionRange(edit.from, edit.to);

        // Inserting as typing keeps the edit on the textarea's own undo stack.
        if (!document.execCommand('insertText', false, edit.insert)) {
            element.setRangeText(edit.insert, edit.from, edit.to);
            onChange(element.value);
        }

        element.setSelectionRange(edit.selectionStart, edit.selectionEnd);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        const tool = TOOLS.find(
            (candidate) => candidate.key === event.key.toLowerCase(),
        );

        if (tool && hasModKey(event) && !event.shiftKey && !event.altKey) {
            event.preventDefault();
            apply(tool.format);
        }
    };

    const showPreview = async () => {
        setMode('preview');

        if (preview?.key === previewKey || value.trim() === '') {
            return;
        }

        request.transform(() => ({ subject, body: value }));

        try {
            const response = await request.submit(
                EmailPreviewController.store(),
            );

            setPreview({ key: previewKey, html: response.html });
        } catch {
            toast.error(
                <span className="font-mono text-[13px]">
                    fatal: could not render the preview
                </span>,
            );
        }
    };

    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap items-center gap-2">
                <SegmentedControl
                    label="Editor mode"
                    name="mode"
                    value={mode}
                    onChange={(next) =>
                        next === 'preview' ? showPreview() : setMode('write')
                    }
                    options={[
                        { value: 'write', label: 'Write' },
                        { value: 'preview', label: 'Preview' },
                    ]}
                />
                <div
                    role="toolbar"
                    aria-label="Formatting"
                    className={cn(
                        'flex flex-wrap items-center gap-0.5',
                        mode === 'preview' && 'invisible',
                    )}
                >
                    {TOOLS.map((tool) => (
                        <Button
                            key={tool.format}
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground"
                            aria-label={tool.label}
                            title={
                                tool.key
                                    ? `${tool.label} (${combo('mod', tool.key.toUpperCase())})`
                                    : tool.label
                            }
                            disabled={disabled}
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => apply(tool.format)}
                        >
                            <tool.icon />
                        </Button>
                    ))}
                </div>
            </div>

            <div className="relative h-[32rem] overflow-hidden rounded-md border">
                <textarea
                    ref={textarea}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    onKeyDown={onKeyDown}
                    disabled={disabled}
                    aria-label="Body"
                    aria-invalid={error ? true : undefined}
                    placeholder={
                        'Hey,\n\nHere is what shipped this week.\n\n## New\n\n- **Something** you will notice'
                    }
                    className={cn(
                        'size-full resize-none overflow-x-hidden bg-transparent p-3 font-mono text-base outline-none placeholder:text-muted-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 md:text-[13px]',
                        mode === 'preview' && 'invisible',
                    )}
                    data-test="email-body"
                />
                {mode === 'preview' && (
                    <div className="absolute inset-0 bg-white">
                        {value.trim() === '' ? (
                            <p className="p-4 text-sm text-neutral-500">
                                Nothing to preview yet.
                            </p>
                        ) : preview?.key === previewKey ? (
                            <iframe
                                title="Email preview"
                                srcDoc={preview.html}
                                sandbox=""
                                className="size-full"
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center">
                                <Spinner className="text-neutral-500" />
                            </div>
                        )}
                    </div>
                )}
            </div>
            <InputError message={error} />
        </div>
    );
}

function FilterSelect({
    label,
    value,
    options,
    labels,
    onChange,
}: {
    label: string;
    value: string;
    options: string[];
    labels: Record<string, string>;
    onChange: (value: string) => void;
}) {
    const id = useId();

    return (
        <div className="grid gap-1.5">
            <label
                id={id}
                className="font-mono text-[13px] text-muted-foreground"
            >
                {label}
            </label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger aria-labelledby={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option} value={option}>
                            {labels[option] ?? option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

export default function Emails({
    activity,
    signup,
    activities,
    signups,
    drafts,
    recipients,
    testAddress,
}: {
    activity: string;
    signup: string;
    activities: string[];
    signups: string[];
    drafts: string[];
    recipients: number;
    testAddress: string | null;
}) {
    const testId = useId();
    const [isConfirming, setIsConfirming] = useState(false);
    const form = useForm({
        subject: '',
        body: '',
        address: testAddress ?? '',
    });
    const [isTesting, setIsTesting] = useState(false);
    const draft = useHttp<{ draft: string }, { subject: string; body: string }>(
        { draft: '' },
    );
    const isBusy = form.processing || isTesting || draft.processing;
    const isWritten =
        form.data.subject.trim() !== '' && form.data.body.trim() !== '';

    const filter = (filters: { activity: string; signup: string }) =>
        router.get(
            EmailController.create({ query: filters }),
            {},
            {
                only: ['activity', 'signup', 'recipients'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );

    const generate = async (kind: string) => {
        const previous = {
            subject: form.data.subject,
            body: form.data.body,
        };

        draft.transform(() => ({ draft: kind }));

        try {
            const response = await draft.submit(EmailDraftController.store());

            form.setData((data) => ({ ...data, ...response }));

            toast(
                <span className="font-mono text-[13px]">
                    Drafted the {DRAFTS[kind]?.toLowerCase()} from the changelog
                </span>,
                previous.body.trim() === ''
                    ? undefined
                    : {
                          action: {
                              label: 'Undo',
                              onClick: () =>
                                  form.setData((data) => ({
                                      ...data,
                                      ...previous,
                                  })),
                          },
                      },
            );
        } catch {
            toast.error(
                <span className="font-mono text-[13px]">
                    fatal: could not draft the email. Try again.
                </span>,
            );
        }
    };

    const sendTest = () =>
        router.post(
            TestEmailController.store().url,
            {
                address: form.data.address,
                subject: form.data.subject,
                body: form.data.body,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setIsTesting(true),
                onFinish: () => setIsTesting(false),
                onError: (errors) => form.setError(errors),
                onSuccess: () => form.clearErrors(),
            },
        );

    const send = () => {
        form.transform((data) => ({
            subject: data.subject,
            body: data.body,
            activity,
            signup,
        }));

        form.submit(EmailController.store(), {
            preserveScroll: true,
            onSuccess: () => {
                setIsConfirming(false);
                form.reset('subject', 'body');
            },
        });
    };

    return (
        <>
            <Head title="Emails" />

            <div className="grid max-w-5xl gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <Panel
                    title="Compose"
                    meta="markdown"
                    actions={
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={isBusy}
                                    data-test="email-draft-button"
                                >
                                    {draft.processing ? (
                                        <Spinner />
                                    ) : (
                                        <Sparkles />
                                    )}
                                    Draft with AI
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {drafts.map((kind) => (
                                    <DropdownMenuItem
                                        key={kind}
                                        onSelect={() => generate(kind)}
                                    >
                                        {DRAFTS[kind] ?? kind}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    }
                >
                    <div className="grid gap-4">
                        <div className="grid gap-1.5">
                            <input
                                value={form.data.subject}
                                onChange={(event) =>
                                    form.setData('subject', event.target.value)
                                }
                                disabled={isBusy}
                                maxLength={200}
                                aria-label="Subject"
                                aria-invalid={
                                    form.errors.subject ? true : undefined
                                }
                                placeholder="Subject"
                                className={cn(inputClassName, 'font-medium')}
                                data-test="email-subject"
                            />
                            <InputError message={form.errors.subject} />
                        </div>
                        <Editor
                            value={form.data.body}
                            onChange={(body) => form.setData('body', body)}
                            subject={form.data.subject}
                            error={form.errors.body}
                            disabled={isBusy}
                        />
                    </div>
                </Panel>

                <div className="grid content-start gap-4">
                    <Panel
                        title="Audience"
                        meta={`${formatCount(recipients)} ${recipients === 1 ? 'person' : 'people'}`}
                    >
                        <div className="grid gap-3">
                            <FilterSelect
                                label="last seen"
                                value={activity}
                                options={activities}
                                labels={ACTIVITIES}
                                onChange={(value) =>
                                    filter({ activity: value, signup })
                                }
                            />
                            <FilterSelect
                                label="signed up"
                                value={signup}
                                options={signups}
                                labels={SIGNUPS}
                                onChange={(value) =>
                                    filter({ activity, signup: value })
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Never reaches banned people, bots, or anyone who
                                unsubscribed.
                            </p>
                        </div>
                    </Panel>

                    <Panel title="Test">
                        <form
                            className="grid gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                sendTest();
                            }}
                        >
                            <label
                                htmlFor={testId}
                                className="font-mono text-[13px] text-muted-foreground"
                            >
                                send to
                            </label>
                            <input
                                id={testId}
                                type="email"
                                value={form.data.address}
                                onChange={(event) =>
                                    form.setData('address', event.target.value)
                                }
                                disabled={isBusy}
                                required
                                placeholder="you@example.com"
                                className={cn(inputClassName, 'font-mono')}
                                data-test="email-test-address"
                            />
                            <InputError message={form.errors.address} />
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={isBusy || !isWritten}
                                data-test="email-test-button"
                            >
                                {isTesting ? <Spinner /> : <Send />}
                                Send test
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                Sent right away, with “[Test]” before the
                                subject.
                            </p>
                        </form>
                    </Panel>

                    <Dialog
                        open={isConfirming}
                        onOpenChange={(open) =>
                            !form.processing && setIsConfirming(open)
                        }
                    >
                        <DialogTrigger asChild>
                            <Button
                                size="lg"
                                disabled={
                                    isBusy || !isWritten || recipients === 0
                                }
                                className="tabular-nums"
                                data-test="email-send-button"
                            >
                                Send to {formatCount(recipients)}{' '}
                                {recipients === 1 ? 'person' : 'people'}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                Send “{form.data.subject}”?
                            </DialogTitle>
                            <DialogDescription>
                                It goes out to {formatCount(recipients)}{' '}
                                {recipients === 1 ? 'person' : 'people'} and
                                can't be taken back. Send yourself a test first
                                if you haven't.
                            </DialogDescription>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={form.processing}
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="button"
                                    onClick={send}
                                    disabled={form.processing}
                                    data-test="email-confirm-send-button"
                                >
                                    {form.processing && <Spinner />}
                                    Send
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </>
    );
}

Emails.layout = OperationsLayout;
