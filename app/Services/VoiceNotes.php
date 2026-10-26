<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Voice notes arrive as Opus in WebM, about 180 KB a minute, or as 16 kHz, mono, 16-bit PCM WAV from browsers that
 * can't record Opus. The server never decodes either: the WebM container is walked element by element and each Opus
 * packet is only measured from its first byte, and the WAV is checked as it is. Either way the stored file is written
 * anew from the audio alone, Opus as MP4, so nothing that came along with it, such as metadata or tags, is kept.
 * Lengths and offsets are in bytes, hence the 8-bit encoding.
 */
final readonly class VoiceNotes
{
    public const int MIN_MILLISECONDS = 500;

    /**
     * A minute, with a moment for the recorder to stop.
     */
    public const int MAX_MILLISECONDS = 61_000;

    /**
     * A minute of WAV, with room for its header and any chunks a recorder adds. Opus takes a tenth of it.
     */
    public const int MAX_UPLOAD_KILOBYTES = 1950;

    public const int BARS = 48;

    private const int WAV_RATE = 16000;

    private const int WAV_BYTES_PER_SECOND = self::WAV_RATE * 2;

    private const int OPUS_RATE = 48000;

    private const int EBML = 0x1A45DFA3;

    private const int DOC_TYPE = 0x4282;

    private const int SEGMENT = 0x18538067;

    private const int TRACKS = 0x1654AE6B;

    private const int TRACK_ENTRY = 0xAE;

    private const int TRACK_NUMBER = 0xD7;

    private const int TRACK_TYPE = 0x83;

    private const int CODEC_ID = 0x86;

    private const int CODEC_PRIVATE = 0x63A2;

    private const int CLUSTER = 0x1F43B675;

    private const int SIMPLE_BLOCK = 0xA3;

    private const int BLOCK_GROUP = 0xA0;

    private const int BLOCK = 0xA1;

    /**
     * The elements that sit right under the segment, where a cluster of unknown size ends.
     */
    private const array TOP_LEVEL = [0x114D9B74, 0x1549A966, self::TRACKS, self::CLUSTER, 0x1C53BB6B, 0x1254C367, 0x1043A770, 0x1941A469];

    /**
     * The upload as the file to store and how long it plays, or null when it isn't a voice note.
     *
     * @return array{file: string, duration_ms: int}|null
     */
    public function rebuild(string $upload): ?array
    {
        $samples = $this->samples($upload);

        if ($samples !== null) {
            return ['file' => $this->wav($samples), 'duration_ms' => intdiv(mb_strlen($samples, '8bit') * 1000, self::WAV_BYTES_PER_SECOND)];
        }

        $opus = $this->opus($upload);

        return $opus === null ? null : ['file' => $this->mp4(...$opus), 'duration_ms' => $opus['milliseconds']];
    }

    public function type(string $file): string
    {
        return str_starts_with($file, 'RIFF') ? 'audio/wav' : 'audio/mp4';
    }

    private function samples(string $wav): ?string
    {
        if (! str_starts_with($wav, 'RIFF') || mb_substr($wav, 8, 4, '8bit') !== 'WAVE') {
            return null;
        }

        $format = null;
        $offset = 12;

        while ($offset + 8 <= mb_strlen($wav, '8bit')) {
            $id = mb_substr($wav, $offset, 4, '8bit');
            $size = $this->uint(strrev(mb_substr($wav, $offset + 4, 4, '8bit')));

            if ($offset + 8 + $size > mb_strlen($wav, '8bit')) {
                return null;
            }

            if ($id === 'fmt ' && $size >= 16) {
                $format = mb_substr($wav, $offset + 8, 16, '8bit');
            }

            if ($id === 'data') {
                return $format === $this->wavFormat()
                    && $size % 2 === 0
                    && $this->isLongEnough(intdiv($size * 1000, self::WAV_BYTES_PER_SECOND))
                        ? mb_substr($wav, $offset + 8, $size, '8bit')
                        : null;
            }

            $offset += 8 + $size + $size % 2;
        }

        return null;
    }

    private function wav(string $samples): string
    {
        return 'RIFF'.pack('V', 36 + mb_strlen($samples, '8bit')).'WAVEfmt '.pack('V', 16).$this->wavFormat().'data'.pack('V', mb_strlen($samples, '8bit')).$samples;
    }

    private function wavFormat(): string
    {
        return pack('vvVVvv', 1, 1, self::WAV_RATE, self::WAV_BYTES_PER_SECOND, 2, 16);
    }

    /**
     * The one Opus track of a WebM file: its channels, the samples its decoder skips, and its packets with how many
     * samples each one holds, read from the packets themselves rather than from the file's timestamps.
     *
     * @return array{channels: int, preSkip: int, packets: list<array{int, string}>, milliseconds: int}|null
     */
    private function opus(string $webm): ?array
    {
        $top = $this->children($webm, 0, mb_strlen($webm, '8bit'));

        if (count($top ?? []) !== 2 || $top[0][0] !== self::EBML || $top[1][0] !== self::SEGMENT
            || $this->child($webm, $top[0], self::DOC_TYPE) !== 'webm') {
            return null;
        }

        $segment = $this->children($webm, $top[1][1], $top[1][2]) ?? [];
        $tracks = array_values(array_filter($segment, fn (array $element): bool => $element[0] === self::TRACKS));
        $entries = count($tracks) === 1 ? ($this->children($webm, $tracks[0][1], $tracks[0][2]) ?? []) : [];

        if (count($entries) !== 1 || $entries[0][0] !== self::TRACK_ENTRY
            || $this->uint((string) $this->child($webm, $entries[0], self::TRACK_TYPE)) !== 2
            || $this->child($webm, $entries[0], self::CODEC_ID) !== 'A_OPUS') {
            return null;
        }

        $head = (string) $this->child($webm, $entries[0], self::CODEC_PRIVATE);
        $track = $this->uint((string) $this->child($webm, $entries[0], self::TRACK_NUMBER));

        if (mb_strlen($head, '8bit') < 19 || ! str_starts_with($head, 'OpusHead') || (ord($head[8]) & 0xF0) !== 0
            || ! in_array(ord($head[9]), [1, 2], true) || ord($head[18]) !== 0) {
            return null;
        }

        $preSkip = $this->uint(strrev(mb_substr($head, 10, 2, '8bit')));
        $packets = [];
        $at = 0;

        foreach ($segment as $element) {
            $blocks = $element[0] === self::CLUSTER ? $this->children($webm, $element[1], $element[2]) : [];

            foreach ($blocks ?? [null] as $block) {
                $block = $block !== null && $block[0] === self::BLOCK_GROUP ? $this->childElement($webm, $block, self::BLOCK) : $block;

                if ($block !== null && $block[0] !== self::SIMPLE_BLOCK && $block[0] !== self::BLOCK) {
                    continue;
                }

                $packet = $block === null ? null : $this->packet($webm, $block, $track);
                $samples = $packet === null ? null : $this->packetSamples($packet);

                if ($packet === null || $samples === null) {
                    return null;
                }

                $packets[] = [$samples, $packet];
                $at += $samples;
            }
        }

        $milliseconds = intdiv(max(0, $at - $preSkip) * 1000, self::OPUS_RATE);

        return $packets !== [] && $this->isLongEnough($milliseconds)
            ? ['channels' => ord($head[9]), 'preSkip' => $preSkip, 'packets' => $packets, 'milliseconds' => $milliseconds]
            : null;
    }

    /**
     * The frame of a block on the Opus track, which must hold exactly one, as recorders write them.
     *
     * @param  array{int, int, int}  $block
     */
    private function packet(string $webm, array $block, int $track): ?string
    {
        $number = $this->vint($webm, $block[1], $block[2], 8, keepMarker: false);

        if ($number === null || $number[0] !== $track || $block[2] <= $block[1] + $number[1] + 3
            || (ord($webm[$block[1] + $number[1] + 2]) & 0x06) !== 0) {
            return null;
        }

        return mb_substr($webm, $block[1] + $number[1] + 3, $block[2] - $block[1] - $number[1] - 3, '8bit');
    }

    /**
     * How many 48 kHz samples an Opus packet holds, read from its table of contents byte (RFC 6716, section 3.1),
     * or null when it can't be a packet.
     */
    private function packetSamples(string $packet): ?int
    {
        $toc = ord($packet[0]);
        $config = $toc >> 3;

        $frame = match (true) {
            $config < 12 => [480, 960, 1920, 2880][$config % 4],
            $config < 16 => [480, 960][$config % 2],
            default => [120, 240, 480, 960][$config % 4],
        };

        $frames = match ($toc & 3) {
            0 => 1,
            1, 2 => 2,
            default => mb_strlen($packet, '8bit') > 1 ? ord($packet[1]) & 0x3F : 0,
        };

        return $frames > 0 && $frame * $frames <= 5760 ? $frame * $frames : null;
    }

    /**
     * A fresh MP4 file holding the one Opus track and its packets, and nothing else, laid out as the Opus in ISO
     * BMFF specification has it. MP4 rather than WebM, since it is the container every browser plays Opus from,
     * Safari on iOS included. The edit list hides the samples the decoder skips at the start.
     *
     * @param  list<array{int, string}>  $packets
     */
    private function mp4(int $channels, int $preSkip, array $packets, int $milliseconds): string
    {
        $samples = array_sum(array_column($packets, 0));
        $matrix = pack('N9', 0x10000, 0, 0, 0, 0x10000, 0, 0, 0, 0x40000000);
        $durations = [];

        foreach ($packets as [$length]) {
            $last = count($durations) - 1;

            if ($last >= 0 && $durations[$last][1] === $length) {
                $durations[$last][0]++;
            } else {
                $durations[] = [1, $length];
            }
        }

        $moov = fn (int $offset): string => $this->box('moov',
            $this->box('mvhd', pack('N6', 0, 0, 0, 1000, $milliseconds, 0x10000).pack('n', 0x100).str_repeat("\0", 10).$matrix.str_repeat("\0", 24).pack('N', 2))
            .$this->box('trak',
                $this->box('tkhd', pack('N6', 3, 0, 0, 1, 0, $milliseconds).str_repeat("\0", 8).pack('nnnn', 0, 0, 0x100, 0).$matrix.pack('NN', 0, 0))
                .$this->box('edts', $this->box('elst', pack('N5', 0, 1, $milliseconds, $preSkip, 0x10000)))
                .$this->box('mdia',
                    $this->box('mdhd', pack('N5', 0, 0, 0, self::OPUS_RATE, $samples).pack('nn', 0x55C4, 0))
                    .$this->box('hdlr', pack('N2', 0, 0).'soun'.str_repeat("\0", 12)."SoundHandler\0")
                    .$this->box('minf',
                        $this->box('smhd', pack('N2', 0, 0))
                        .$this->box('dinf', $this->box('dref', pack('N2', 0, 1).$this->box('url ', pack('N', 1))))
                        .$this->box('stbl',
                            $this->box('stsd', pack('N2', 0, 1).$this->box('Opus',
                                str_repeat("\0", 6).pack('n', 1).str_repeat("\0", 8).pack('nnnn', $channels, 16, 0, 0).pack('N', self::OPUS_RATE << 16)
                                .$this->box('dOps', pack('CCnNnC', 0, $channels, $preSkip, self::OPUS_RATE, 0, 0)),
                            ))
                            .$this->box('stts', pack('N2', 0, count($durations)).implode('', array_map(fn (array $run): string => pack('N2', ...$run), $durations)))
                            .$this->box('stsc', pack('N5', 0, 1, 1, count($packets), 1))
                            .$this->box('stsz', pack('N3', 0, 0, count($packets)).implode('', array_map(fn (array $packet): string => pack('N', mb_strlen($packet[1], '8bit')), $packets)))
                            .$this->box('stco', pack('N3', 0, 1, $offset)),
                        ),
                    ),
                ),
            ),
        );

        $ftyp = $this->box('ftyp', 'isom'.pack('N', 0x200).'isomiso2mp41');
        $offset = mb_strlen($ftyp, '8bit') + mb_strlen($moov(0), '8bit') + 8;

        return $ftyp.$moov($offset).$this->box('mdat', implode('', array_column($packets, 1)));
    }

    private function box(string $type, string $data): string
    {
        return pack('N', 8 + mb_strlen($data, '8bit')).$type.$data;
    }

    /**
     * The elements from the offset to the end, as their id, where their data starts, and where it ends. A segment or
     * cluster of unknown size, as live recorders write them, runs to the end or to the next element of the segment.
     *
     * @return list<array{int, int, int}>|null
     */
    private function children(string $webm, int $offset, int $end): ?array
    {
        $children = [];

        while ($offset < $end) {
            $id = $this->vint($webm, $offset, $end, 4, keepMarker: true);
            $size = $id === null ? null : $this->vint($webm, $offset + $id[1], $end, 8, keepMarker: false);

            if ($id === null || $size === null) {
                return null;
            }

            $data = $offset + $id[1] + $size[1];
            $dataEnd = match (true) {
                $size[0] !== -1 => $data + $size[0],
                $id[0] === self::SEGMENT => $end,
                $id[0] === self::CLUSTER => $this->clusterEnd($webm, $data, $end),
                default => null,
            };

            if ($dataEnd === null || $dataEnd > $end) {
                return null;
            }

            $children[] = [$id[0], $data, $dataEnd];
            $offset = $dataEnd;
        }

        return $children;
    }

    private function clusterEnd(string $webm, int $offset, int $end): ?int
    {
        while ($offset < $end) {
            $id = $this->vint($webm, $offset, $end, 4, keepMarker: true);
            $size = $id === null ? null : $this->vint($webm, $offset + $id[1], $end, 8, keepMarker: false);

            if ($id !== null && in_array($id[0], self::TOP_LEVEL, true)) {
                return $offset;
            }

            if ($id === null || $size === null || $size[0] === -1) {
                return null;
            }

            $offset += $id[1] + $size[1] + $size[0];
        }

        return $end;
    }

    /**
     * The data of the first child of an element with the given id.
     *
     * @param  array{int, int, int}  $parent
     */
    private function child(string $webm, array $parent, int $id): ?string
    {
        $child = $this->childElement($webm, $parent, $id);

        return $child === null ? null : mb_substr($webm, $child[1], $child[2] - $child[1], '8bit');
    }

    /**
     * @param  array{int, int, int}  $parent
     * @return array{int, int, int}|null
     */
    private function childElement(string $webm, array $parent, int $id): ?array
    {
        return array_find($this->children($webm, $parent[1], $parent[2]) ?? [], fn (array $child): bool => $child[0] === $id);
    }

    /**
     * An EBML variable-length integer: an id keeps its length marker, a size doesn't, and a size of all ones is
     * unknown (-1). Returns the value and how many bytes it took.
     *
     * @return array{int, int}|null
     */
    private function vint(string $webm, int $offset, int $end, int $maxLength, bool $keepMarker): ?array
    {
        if ($offset >= $end) {
            return null;
        }

        $first = ord($webm[$offset]);
        $length = 1;

        while ($length <= 8 && ($first & (0x80 >> ($length - 1))) === 0) {
            $length++;
        }

        if ($length > $maxLength || $offset + $length > $end) {
            return null;
        }

        $value = $keepMarker ? $first : $first & (0xFF >> $length);
        $isUnknown = ! $keepMarker && $value === (0xFF >> $length);

        for ($index = 1; $index < $length; $index++) {
            $value = ($value << 8) | ord($webm[$offset + $index]);
            $isUnknown = $isUnknown && ord($webm[$offset + $index]) === 0xFF;
        }

        return [$isUnknown ? -1 : $value, $length];
    }

    /**
     * A big-endian unsigned integer of up to seven bytes.
     */
    private function uint(string $bytes): int
    {
        return array_reduce(mb_str_split(mb_substr($bytes, -7, null, '8bit'), 1, '8bit'), fn (int $value, string $byte): int => ($value << 8) | ord($byte[0]), 0);
    }

    private function isLongEnough(int $milliseconds): bool
    {
        return $milliseconds >= self::MIN_MILLISECONDS && $milliseconds <= self::MAX_MILLISECONDS;
    }
}
