import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Ellipsis, LockKeyhole, Quote, Star } from 'lucide-react';
import { CommitReactionPicker } from '@/components/commit-reaction-picker';
import { CommitReactionTooltip } from '@/components/commit-reaction-tooltip';
import { TooltipProvider } from '@/components/ui/tooltip';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { ReactionState } from '@/lib/commit-reaction-cache';
import '../../css/app.css';

const repositories = [
    { id: 'archive', repository: 'you/dev-null-archive', private: true },
    { id: 'build', repository: 'you/build-log', private: false },
];
const storageKey = 'dev-null:static-playground:v1';
type Saved = { reactions: ReactionState['reactions']; starred: boolean };
function readSaved(): Saved {
    try {
        const saved = JSON.parse(localStorage.getItem(storageKey) ?? 'null');
        if (saved && typeof saved.starred === 'boolean' && Array.isArray(saved.reactions) && saved.reactions.every((r: ReactionState['reactions'][number]) => typeof r.emoji === 'string' && typeof r.public === 'boolean' && Array.isArray(r.repositories))) return saved;
    } catch { /* Storage may be unavailable. The playground still works. */ }
    return { reactions: [], starred: false };
}
function Playground() {
    const [saved, setSaved] = useState(readSaved);
    const [quote, setQuote] = useState(false);
    useEffect(() => {
        try { localStorage.setItem(storageKey, JSON.stringify(saved)); } catch { /* Keep the session usable without storage. */ }
    }, [saved]);
    const remove = (emoji: string) => setSaved(s => ({ ...s, reactions: s.reactions.filter(r => r.emoji !== emoji) }));
    return <TooltipProvider><main className="mx-auto max-w-5xl px-5 py-10 sm:px-8 sm:py-14">
        <h1 className="mb-4 text-base font-semibold">Try a reaction</h1>
        <article className="flex gap-3 overflow-hidden rounded-lg border p-4 pb-0 sm:gap-4" aria-label="Demo post by Alex">
            <div className="relative flex w-10 shrink-0 justify-center sm:w-12">
                <div className="absolute top-10 bottom-0 w-0.5 bg-cyan-600" />
                <div className="relative flex size-10 items-center justify-center rounded-full border-2 border-cyan-600 bg-muted font-mono text-xs text-muted-foreground ring-2 ring-background sm:size-12">A(</div>
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm"><strong>Alex (demo)</strong><span className="font-mono text-xs text-muted-foreground">@demo-author · 1h</span><span className="ml-auto font-mono text-xs text-muted-foreground">b6a924f</span></div>
                <p className="mt-1 text-sm leading-relaxed sm:text-base">Shipped a smaller build and a faster test suite. What should we automate next?</p>
                <div className="mt-2 flex items-start gap-3 border-b pb-4">
                    <button className="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-muted-foreground hover:bg-accent" aria-label={saved.starred ? 'Unstar commit' : 'Star commit'} aria-pressed={saved.starred} onClick={() => setSaved(s => ({ ...s, starred: !s.starred }))}><Star className="size-4" fill={saved.starred ? 'currentColor' : 'none'} />{saved.starred && <span className="text-xs">1</span>}</button>
                    <button className="inline-flex items-center gap-1.5 rounded-md py-1 text-sm text-muted-foreground hover:text-foreground" aria-expanded={quote} onClick={() => setQuote(!quote)}><Quote className="size-4" />Quote</button>
                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-1">
                        <CommitReactionPicker commitId="demo" userId="you" state={{ reactions: saved.reactions, repositories }} disabled={false} onRefresh={() => {}} showSettings={false} onPick={(emoji, destinations) => setSaved(s => ({ ...s, reactions: [...s.reactions.filter(r => r.emoji !== emoji), { emoji, ...destinations }] }))} onRemove={remove} onClear={() => setSaved(s => ({ ...s, reactions: [] }))} />
                        {saved.reactions.map(r => <CommitReactionTooltip key={r.emoji} viewerId="you" privateOnly={!r.public} reaction={{ emoji: r.emoji, count: 1, reacted: true, users: [{ id: 'you', name: 'You', username: 'you' }] }}><button onClick={() => remove(r.emoji)} aria-label={`Remove ${r.emoji} reaction`} aria-pressed="true" className="inline-flex h-7 items-center gap-1 rounded-full border border-primary/30 bg-primary/10 px-2 text-sm"><span>{r.emoji}</span>{r.public ? <span className="text-xs">1</span> : <LockKeyhole className="size-3 text-muted-foreground" />}</button></CommitReactionTooltip>)}
                    </div>
                    <Popover><PopoverTrigger asChild><button aria-label="More options" className="rounded-md p-1 text-muted-foreground hover:bg-accent"><Ellipsis className="size-4" /></button></PopoverTrigger><PopoverContent align="end" className="w-52 p-1"><button className="w-full rounded-md p-2 text-left text-sm hover:bg-accent" onClick={() => { setSaved({ reactions: [], starred: false }); setQuote(false); }}>Reset playground</button></PopoverContent></Popover>
                </div>
                {quote && <div className="py-4"><label htmlFor="quote" className="mb-2 block text-sm font-medium">Quote this post</label><textarea id="quote" autoFocus placeholder="Add your thoughts…" className="w-full rounded-md border bg-background p-3 text-sm" /><p className="mt-1 text-xs text-muted-foreground">Demo only · nothing is published.</p></div>}
            </div>
        </article>
    </main></TooltipProvider>;
}
createRoot(document.getElementById('root')!).render(<Playground />);
