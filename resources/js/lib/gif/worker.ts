/// <reference lib="webworker" />
import { parseGIF, decompressFrame } from 'gifuct-js';
import {
    BufferTarget,
    EncodedPacket,
    EncodedVideoPacketSource,
    Output,
    WebMOutputFormat,
} from 'mediabunny';

const scope = self as unknown as DedicatedWorkerGlobalScope;
const MAX_BYTES = 10 * 1024 * 1024;
const progress = (message: string) => scope.postMessage({ progress: message });

class NativeGifDecodingError extends Error {}

/** Bound parser allocations before gifuct-js builds objects for every block. */
function validateGifStructure(buffer: ArrayBuffer): void {
    const bytes = new Uint8Array(buffer);
    let position = 0;
    let frames = 0;
    let blocks = 0;
    let subBlocks = 0;
    const invalid = () => new Error('The GIF is incomplete or malformed.');
    const skip = (length: number) => {
        position += length;
        if (position > bytes.length) throw invalid();
    };
    const read = () => {
        if (position >= bytes.length) throw invalid();
        return bytes[position++];
    };
    const skipTable = (packed: number) => {
        if (packed & 0x80) skip(3 * (1 << ((packed & 7) + 1)));
    };
    const skipSubBlocks = () => {
        for (let size = read(); size !== 0; size = read()) {
            if (++subBlocks > 65_536)
                throw new Error('This GIF contains too many data blocks.');
            skip(size);
        }
    };

    const signature = new TextDecoder().decode(bytes.subarray(0, 6));
    if (signature !== 'GIF87a' && signature !== 'GIF89a')
        throw new Error('This file is not a GIF.');
    skip(10);
    const packed = read();
    skip(2);
    skipTable(packed);

    while (position < bytes.length) {
        const block = read();
        if (block === 0x3b && frames > 0) return;
        if (++blocks > 4096)
            throw new Error('This GIF contains too many blocks.');

        switch (block) {
            case 0x2c:
                if (++frames > 600)
                    throw new Error('GIFs must contain 1–600 frames.');
                skip(8);
                skipTable(read());
                skip(1); // LZW minimum code size.
                skipSubBlocks();
                break;
            case 0x21:
                if (![0xf9, 0xfe, 0xff, 0x01].includes(read())) throw invalid();
                skipSubBlocks();
                break;
            default:
                throw invalid();
        }
    }

    throw invalid();
}

function isDecoderFailure(error: unknown): boolean {
    return (
        error instanceof DOMException &&
        [
            'NotSupportedError',
            'EncodingError',
            'OperationError',
            'DataError',
        ].includes(error.name)
    );
}

scope.onmessage = async ({ data: file }: MessageEvent<File>) => {
    let decoder: ImageDecoder | undefined;
    let encoder: VideoEncoder | undefined;
    let output: Output | undefined;
    try {
        progress('Reading GIF…');
        if (file.size > MAX_BYTES)
            throw new Error('GIFs must be at most 10 MB.');
        const bytes = await file.arrayBuffer();
        validateGifStructure(bytes);
        const gif = parseGIF(bytes);
        const frames = gif.frames.filter((frame) => 'image' in frame);
        const { width, height } = gif.lsd;
        if (!width || !height || width > 6000 || height > 6000)
            throw new Error(
                'GIFs must contain at most 4 million pixels, with neither side above 6000 pixels.',
            );
        if (frames.length === 1) {
            scope.postMessage({ static: true });
            return;
        }
        if (width * height > 4_000_000) {
            throw new Error(
                'Animated GIFs must contain at most 4 million source pixels.',
            );
        }
        // Browser-style minimum: delays below 20 ms become 100 ms in every pipeline.
        const durations = frames.map((frame) => {
            const ms = (frame.gce?.delay ?? 0) * 10;
            return ms < 20 ? 100_000 : ms * 1000;
        });
        if (durations.reduce((a, b) => a + b, 0) > 30_000_000)
            throw new Error('Animated GIFs must be at most 30 seconds long.');
        for (const frame of frames) {
            const d = frame.image.descriptor;
            if (
                !d.width ||
                !d.height ||
                d.left + d.width > width ||
                d.top + d.height > height
            )
                throw new Error('The GIF contains invalid frame dimensions.');
        }
        if (!scope.isSecureContext) {
            throw new Error(
                'Animated GIF conversion requires HTTPS or localhost. Open this app over HTTPS, or use localhost when running locally.',
            );
        }
        if (
            typeof VideoEncoder === 'undefined' ||
            typeof OffscreenCanvas === 'undefined'
        )
            throw new Error(
                'This browser cannot convert animated GIFs. Try an updated browser or attach a still image.',
            );
        const scale = Math.min(1, 960 / Math.max(width, height));
        const w = Math.max(1, Math.round(width * scale));
        const h = Math.max(1, Math.round(height * scale));
        const config: VideoEncoderConfig = {
            codec: 'vp8',
            width: w,
            height: h,
            bitrate: 1_500_000,
            latencyMode: 'quality',
        };
        if (!(await VideoEncoder.isConfigSupported(config)).supported)
            throw new Error(
                'This browser has no supported VP8 encoder. Try another browser or attach a still image.',
            );
        if (
            typeof ImageDecoder !== 'undefined' &&
            (await ImageDecoder.isTypeSupported('image/gif'))
        ) {
            try {
                decoder = new ImageDecoder({ data: bytes, type: 'image/gif' });
                await decoder.tracks.ready;
                const probe = await decoder.decode({ frameIndex: 0 });
                probe.image.close();
            } catch (error) {
                if (!isDecoderFailure(error)) throw error;
                decoder?.close();
                decoder = undefined;
            }
        }
        const run = async (native: boolean) => {
            const canvas = new OffscreenCanvas(w, h);
            const ctx = canvas.getContext('2d')!;
            const composed = new OffscreenCanvas(width, height);
            const composition = composed.getContext('2d')!;
            const patchCanvas = new OffscreenCanvas(width, height);
            const patchCtx = patchCanvas.getContext('2d')!;
            const target = new BufferTarget();
            output = new Output({ format: new WebMOutputFormat(), target });
            const source = new EncodedVideoPacketSource('vp8');
            output.addVideoTrack(source);
            await output.start();
            let pending = Promise.resolve();
            let encoderError: Error | undefined;
            let encodedSize = 0;
            encoder = new VideoEncoder({
                output: (chunk, meta) => {
                    encodedSize += chunk.byteLength;
                    pending = pending.then(async () => {
                        if (encodedSize > MAX_BYTES)
                            throw new Error(
                                'Converted animation exceeds 10 MB.',
                            );
                        await source.add(
                            EncodedPacket.fromEncodedChunk(chunk),
                            meta,
                        );
                    });
                    // Encoding callbacks cannot await. Observe rejection here, then propagate it below after flush.
                    void pending.catch(() => {});
                },
                error: (error) => {
                    encoderError = error;
                },
            });
            encoder.configure(config);
            let timestamp = 0;
            let poster: Blob | undefined;
            let previous: ReturnType<typeof decompressFrame> | undefined;
            let restore: ImageData | undefined;
            for (let i = 0; i < frames.length; i++) {
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, w, h);
                if (native) {
                    let frame: VideoFrame;
                    try {
                        frame = (await decoder!.decode({ frameIndex: i }))
                            .image;
                    } catch (error) {
                        if (!isDecoderFailure(error)) throw error;
                        throw new NativeGifDecodingError(
                            'Native GIF decoding failed.',
                            { cause: error },
                        );
                    }
                    try {
                        ctx.drawImage(frame, 0, 0, w, h);
                    } finally {
                        frame.close();
                    }
                } else {
                    if (previous?.disposalType === 2) {
                        const d = previous.dims;
                        composition.clearRect(d.left, d.top, d.width, d.height);
                    }
                    if (previous?.disposalType === 3 && restore)
                        composition.putImageData(restore, 0, 0);
                    const frame = decompressFrame(frames[i], gif.gct, true);
                    restore =
                        frame.disposalType === 3
                            ? composition.getImageData(0, 0, width, height)
                            : undefined;
                    const d = frame.dims;
                    patchCanvas.width = d.width;
                    patchCanvas.height = d.height;
                    patchCtx.putImageData(
                        new ImageData(
                            new Uint8ClampedArray(frame.patch),
                            d.width,
                            d.height,
                        ),
                        0,
                        0,
                    );
                    composition.drawImage(patchCanvas, d.left, d.top);
                    ctx.drawImage(composed, 0, 0, w, h);
                    previous = frame;
                }
                if (i === 0)
                    poster = await canvas.convertToBlob({ type: 'image/png' });
                const encoded = new VideoFrame(canvas, {
                    timestamp,
                    duration: durations[i],
                });
                try {
                    encoder.encode(encoded, {
                        keyFrame:
                            i === 0 || timestamp % 1_000_000 < durations[i],
                    });
                } finally {
                    encoded.close();
                }
                await encoder.flush();
                await pending;
                if (encoderError) throw encoderError;
                timestamp += durations[i];
                progress(
                    `Converting GIF… ${Math.round(((i + 1) / frames.length) * 100)}%`,
                );
            }
            encoder.close();
            encoder = undefined;
            source.close();
            await output.finalize();
            output = undefined;
            if (!target.buffer || target.buffer.byteLength > MAX_BYTES)
                throw new Error('Converted animation exceeds 10 MB.');
            return { video: target.buffer, poster };
        };
        let result;
        try {
            result = await run(Boolean(decoder));
        } catch (error) {
            if (!(error instanceof NativeGifDecodingError)) throw error;
            if (encoder?.state !== 'closed') encoder?.close();
            encoder = undefined;
            await output?.cancel();
            output = undefined;
            decoder!.close();
            decoder = undefined;
            progress('Retrying with GIF decoder…');
            result = await run(false);
        }
        scope.postMessage(result, [result.video]);
    } catch (error) {
        if (!(error instanceof Error)) throw error;
        scope.postMessage({
            error: error.message,
        });
    } finally {
        decoder?.close();
        if (encoder?.state !== 'closed') encoder?.close();
        await output?.cancel();
    }
};
