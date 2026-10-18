import { Link } from '@inertiajs/react';
import { CalendarDays, ExternalLink, Presentation } from 'lucide-react';
import TalkController from '@/actions/App/Http/Controllers/TalkController';
import { TalkMedia } from '@/components/talk-media';
import type { TalkSummary, User } from '@/types';

export function TalkCard({
    talk,
    user,
}: {
    talk: TalkSummary;
    user: Pick<User, 'id' | 'username' | 'name'>;
}) {
    const hasRecordingPreview = Boolean(
        talk.recording_url && talk.recording_embed,
    );
    const hasSlidesPreview = Boolean(talk.slides_url && talk.slides_embed);

    return (
        <article className="space-y-3 border-b px-5 py-5 sm:px-6">
            <div className="flex items-start gap-3">
                <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md border bg-muted/40 text-foreground">
                    <Presentation className="size-4" />
                </div>
                <div className="min-w-0 flex-1">
                    <Link
                        href={TalkController.show([user, talk])}
                        className="font-semibold text-foreground hover:underline"
                    >
                        {talk.title}
                    </Link>
                    {talk.event_name && (
                        <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                            {talk.event_name}
                        </p>
                    )}
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        {talk.description}
                    </p>
                    <div className="mt-3 flex flex-wrap items-center gap-3 font-mono text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1.5">
                            <CalendarDays className="size-3.5" />
                            <time dateTime={talk.presented_on}>
                                {talk.presented_on}
                            </time>
                        </span>
                        {talk.recording_url && !hasRecordingPreview && (
                            <a
                                href={talk.recording_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1.5 hover:text-foreground hover:underline"
                            >
                                <ExternalLink className="size-3.5" />
                                Recording
                            </a>
                        )}
                        {talk.slides_url && !hasSlidesPreview && (
                            <a
                                href={talk.slides_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1.5 hover:text-foreground hover:underline"
                            >
                                <ExternalLink className="size-3.5" />
                                Slides
                            </a>
                        )}
                    </div>
                </div>
            </div>

            {talk.recording_url && hasRecordingPreview && (
                <TalkMedia
                    url={talk.recording_url}
                    embed={talk.recording_embed}
                    title={talk.title}
                    label="Recording"
                />
            )}

            {talk.slides_url && hasSlidesPreview && (
                <TalkMedia
                    url={talk.slides_url}
                    embed={talk.slides_embed}
                    title={talk.title}
                    label="Slides"
                />
            )}
        </article>
    );
}
