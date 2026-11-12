import { router } from '@inertiajs/react';
import { Volume2, VolumeX } from 'lucide-react';
import { useState } from 'react';
import { update as membership } from '@/actions/App/Http/Controllers/GuildMembershipController';
import { update as mute } from '@/actions/App/Http/Controllers/GuildMuteController';
import { useFollowHover } from '@/components/follow-button';
import { Button } from '@/components/ui/button';
import { formatCount, formatExactCount } from '@/lib/numbers';
import type { Guild } from '@/types/guild';

export function GuildMemberCount({ guild }: { guild: Guild }) {
    return (
        <span
            className="text-sm text-muted-foreground"
            title={`${formatExactCount(guild.member_count)} members`}
        >
            <span
                className="mr-1 inline-block w-[7ch] text-right font-mono tabular-nums"
                aria-hidden="true"
            >
                {formatCount(guild.member_count)}
            </span>
            <span className="sr-only">
                {formatExactCount(guild.member_count)}{' '}
            </span>
            {guild.member_count === 1 ? 'member' : 'members'}
        </span>
    );
}

export function GuildPreferences({ guild }: { guild: Guild }) {
    const [processing, setProcessing] = useState(false);
    const { isJustToggled, markToggled, onMouseLeave } = useFollowHover();

    function update(kind: 'joined' | 'muted') {
        const key = kind === 'joined' ? 'is_joined' : 'is_muted';
        const value = !guild[key];
        markToggled();
        setProcessing(true);
        router
            .optimistic<{ guilds: Guild[] | null }>((props) => ({
                guilds: props.guilds?.map((item) =>
                    item.id === guild.id
                        ? {
                              ...item,
                              [key]: value,
                              member_count:
                                  item.member_count +
                                  (kind === 'joined' ? (value ? 1 : -1) : 0),
                          }
                        : item,
                ),
            }))
            .put(
                (kind === 'joined' ? membership : mute).url(guild.id),
                { [kind]: value },
                {
                    only: ['guilds'],
                    preserveScroll: true,
                    onSuccess: () => router.flushAll(),
                    onFinish: () => setProcessing(false),
                },
            );
    }

    return (
        <div className="flex shrink-0 items-center gap-2">
            <Button
                variant={guild.is_joined ? 'outline' : 'default'}
                size="sm"
                className="group min-h-9 min-w-[5.5rem]"
                aria-label={`${guild.is_joined ? 'Leave' : 'Join'} ${guild.name}`}
                aria-pressed={guild.is_joined}
                disabled={processing}
                onMouseLeave={onMouseLeave}
                onClick={() => update('joined')}
            >
                {guild.is_joined ? (
                    <span className="grid">
                        <span
                            className={
                                isJustToggled
                                    ? ''
                                    : 'col-start-1 row-start-1 group-hover:invisible'
                            }
                        >
                            Joined
                        </span>
                        {!isJustToggled && (
                            <span className="invisible col-start-1 row-start-1 text-destructive group-hover:visible">
                                Leave
                            </span>
                        )}
                    </span>
                ) : (
                    'Join'
                )}
            </Button>
            <Button
                variant="ghost"
                size="icon"
                className="size-9"
                aria-label={`${guild.is_muted ? 'Unmute' : 'Mute'} ${guild.name}`}
                aria-pressed={guild.is_muted}
                title={
                    guild.is_muted
                        ? 'Unmute this guild in your logs'
                        : 'Mute this guild in your logs'
                }
                disabled={processing}
                onClick={() => update('muted')}
            >
                {guild.is_muted ? (
                    <VolumeX className="size-4 text-primary" />
                ) : (
                    <Volume2 className="size-4" />
                )}
            </Button>
        </div>
    );
}
