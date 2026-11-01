import type { BodySegment } from '@/types';

export type TimeMention = {
    text: string;
    hour: number;
    minute: number;
    timeZone: string;
    date: string | null;
    dayOffset: number;
    inferred: boolean;
};

export type ConvertedTime = TimeMention & { instant: string | null };

// Abbreviations describe a fixed offset. In particular, BST stays UTC+1 even when London is on GMT.
// CST and IST are deliberately absent: each names several different offsets.
const offsets: Record<string, string> = {
    UTC: '+00:00',
    GMT: '+00:00',
    Z: '+00:00',
    BST: '+01:00',
    'BRITISH SUMMER TIME': '+01:00',
    CET: '+01:00',
    CEST: '+02:00',
    EET: '+02:00',
    EEST: '+03:00',
    EST: '-05:00',
    EDT: '-04:00',
    PST: '-08:00',
    PDT: '-07:00',
    MST: '-07:00',
    MDT: '-06:00',
    JST: '+09:00',
    KST: '+09:00',
    AEST: '+10:00',
    AEDT: '+11:00',
    NZST: '+12:00',
    NZDT: '+13:00',
};

const zonePattern = String.raw`(?:UTC|GMT)[+-]\d{1,2}(?::?\d{2})?|[+-]\d{2}:?\d{2}|[A-Za-z_+-]+(?:\/[A-Za-z0-9_+-]+)+|${Object.keys(
    offsets,
)
    .map((zone) => zone.replaceAll(' ', '[ \\t]+'))
    .join('|')}`;
const timePattern = new RegExp(
    String.raw`(?<![\p{L}\p{N}_@/.:+-])(?:(?<date>\d{4}-\d{2}-\d{2})[T \t]+(?:at[ \t]+)?|(?<day>today|tomorrow|yesterday)(?:[ \t]+at)?[ \t]+)?(?<hour>\d{1,2})(?::(?<minute>\d{2}))?(?:[ \t]*(?<meridiem>[ap]\.?m\.?)(?![\p{L}]))?(?:[ \t]*(?:\((?<parenthesizedZone>${zonePattern})\)|(?<zone>${zonePattern})))?(?:[ \t]+(?<dayAfter>today|tomorrow|yesterday))?(?![\p{L}\p{N}_/+:\-])`,
    'giu',
);

/**
 * Detect clock times in prose only. A timezone in the text takes precedence over the author's shared timezone.
 */
export function findTimeMentions(
    segments: BodySegment[],
    authorTimeZone: string | null,
): TimeMention[] {
    const mentions = new Map<string, TimeMention>();

    for (const segment of segments) {
        if (segment.type !== 'text') {
            continue;
        }

        const prose = segment.text.replace(
            /(?:https?:\/\/|www\.)[^\s<>]+|\b[\p{L}\p{N}.-]+\.[a-z]{2,}[/:?#][^\s<>]*/giu,
            '',
        );

        for (const match of prose.matchAll(timePattern)) {
            const groups = match.groups!;
            let hour = Number(groups.hour);
            const minute = Number(groups.minute ?? 0);
            const zone = groups.zone ?? groups.parenthesizedZone;

            if (
                hour > 23 ||
                minute > 59 ||
                (groups.meridiem && (hour < 1 || hour > 12)) ||
                (!groups.minute && !groups.meridiem && !zone)
            ) {
                continue;
            }

            // An unknown or ambiguous abbreviation must not fall back to the author's timezone.
            const followingZone = /^[ \t]+\(?([A-Za-z]{2,5})\b/u.exec(
                prose.slice(match.index + match[0].length),
            )?.[1];
            if (
                !zone &&
                followingZone &&
                (followingZone === followingZone.toUpperCase() ||
                    ['CST', 'IST', 'AST', 'CDT'].includes(
                        followingZone.toUpperCase(),
                    ))
            ) {
                continue;
            }

            if (groups.meridiem) {
                hour =
                    (hour % 12) +
                    (groups.meridiem.toLowerCase().startsWith('p') ? 12 : 0);
            }

            let timeZone = authorTimeZone;

            if (zone) {
                const normalized = zone.toUpperCase().replace(/[ \t]+/g, ' ');
                const offset =
                    /^(?:UTC|GMT)?([+-])(\d{1,2})(?::?(\d{2}))?$/.exec(
                        normalized,
                    );
                timeZone = offset
                    ? `${offset[1]}${offset[2].padStart(2, '0')}:${offset[3] ?? '00'}`
                    : (offsets[normalized] ?? zone);
            }

            if (!timeZone) {
                continue;
            }

            const date = groups.date ?? null;
            const day = (groups.day ?? groups.dayAfter)?.toLowerCase();
            const dayOffset =
                day === 'tomorrow' ? 1 : day === 'yesterday' ? -1 : 0;
            const key = `${date}/${dayOffset}/${hour}/${minute}/${timeZone}`;
            mentions.set(key, {
                text: match[0],
                hour,
                minute,
                timeZone,
                date,
                dayOffset,
                inferred: !zone,
            });
        }
    }

    return [...mentions.values()];
}

/**
 * Load Temporal only when requested. Reject skipped and repeated DST hours instead of silently choosing one.
 */
export async function convertTimeMentions(
    mentions: TimeMention[],
    postedAt: string,
): Promise<ConvertedTime[]> {
    const { Temporal } = await import('@js-temporal/polyfill');
    const posted = Temporal.Instant.from(postedAt);

    return mentions.map((mention) => {
        try {
            const date = mention.date
                ? Temporal.PlainDate.from(mention.date)
                : posted
                      .toZonedDateTimeISO(mention.timeZone)
                      .toPlainDate()
                      .add({ days: mention.dayOffset });
            const source = Temporal.ZonedDateTime.from(
                {
                    timeZone: mention.timeZone,
                    year: date.year,
                    month: date.month,
                    day: date.day,
                    hour: mention.hour,
                    minute: mention.minute,
                },
                { disambiguation: 'reject', overflow: 'reject' },
            );

            return { ...mention, instant: source.toInstant().toString() };
        } catch (error) {
            if (!(error instanceof RangeError)) {
                throw error;
            }

            return { ...mention, instant: null };
        }
    });
}
