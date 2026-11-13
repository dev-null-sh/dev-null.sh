export class PickedImageError extends Error {}

export const maxImages = 3;
// The server's File::max('10mb') allows 10,000 kilobytes.
export const maxImageBytes = 10_000 * 1024;
export const acceptedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
export const invalidImageMessage =
    'Only JPEG, PNG, WebP, or HEIC images up to 50 MB.';

// Images up to this size are read, and the ones over maxImageBytes converted.
const maxReadBytes = 50 * 1024 * 1024;

// The server keeps at most 1,600 pixels per side, so a converted image loses
// nothing it would keep, and a photo that size is a fraction of maxImageBytes.
const maxConvertedDimension = 1600;

const isHeic = (file: File): boolean =>
    ['image/heic', 'image/heif'].includes(file.type) ||
    /\.hei[cf]$/i.test(file.name);

/**
 * HEIC photos, like the ones an iPhone hands to a Mac, and images over
 * maxImageBytes are converted before they are attached.
 */
export const needsConversion = (file: File): boolean =>
    isHeic(file) || file.size > maxImageBytes;

/**
 * Re-encodes the image as WebP at the size the server keeps. A HEIC photo the
 * browser can't decode, as Safari can, is decoded with libheif, which is only
 * loaded then. A browser that can't encode WebP gives PNG, which the server
 * accepts too.
 */
async function convert(file: File): Promise<File> {
    const bitmap = await createImageBitmap(file).catch(
        async (error: unknown) => {
            if (!isHeic(file)) throw error;

            const { heicTo } = await import('heic-to');

            return heicTo({ blob: file, type: 'bitmap' });
        },
    );
    const scale = Math.min(
        1,
        maxConvertedDimension / Math.max(bitmap.width, bitmap.height),
    );
    const canvas = new OffscreenCanvas(
        Math.round(bitmap.width * scale),
        Math.round(bitmap.height * scale),
    );
    canvas
        .getContext('2d')
        ?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await canvas.convertToBlob({
        type: 'image/webp',
        quality: 0.9,
    });
    const extension = blob.type.replace('image/', '');
    const name = `${file.name.replace(/\.[^.]*$/, '')}.${extension}`;

    return new File([blob], name, { type: blob.type });
}

/**
 * Copy provider-backed files before reading metadata or creating preview URLs.
 * Android photo providers can report changing timestamps, causing Chromium to
 * reject later reads with ERR_UPLOAD_FILE_CHANGED (crbug.com/40123366).
 */
async function readPickedImage(file: File): Promise<File> {
    if (!acceptedImageTypes.includes(file.type) && !isHeic(file)) {
        throw new PickedImageError(invalidImageMessage);
    }

    // Bound the copy without inspecting the provider's size/lastModified.
    const reader = file.stream().getReader();
    const chunks: Uint8Array<ArrayBuffer>[] = [];
    let bytes = 0;

    try {
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            bytes += value.byteLength;
            if (bytes > maxReadBytes) {
                throw new PickedImageError(invalidImageMessage);
            }
            chunks.push(new Uint8Array(value));
        }
    } finally {
        await reader.cancel().catch(() => {});
        reader.releaseLock();
    }

    if (bytes === 0) {
        throw new PickedImageError(
            'This image is empty. Please select another image.',
        );
    }

    const copy = new File(chunks, file.name, { type: file.type });

    return needsConversion(copy) ? convert(copy) : copy;
}

export async function readPickedImages(
    files: File[],
    capacity = maxImages,
): Promise<{ images: File[]; errors: unknown[] }> {
    const images: File[] = [];
    const errors: unknown[] = [];

    for (const file of files) {
        if (images.length >= capacity) {
            errors.push(
                new PickedImageError(`Up to ${maxImages} images per commit.`),
            );
            break;
        }

        try {
            images.push(await readPickedImage(file));
        } catch (error) {
            errors.push(error);
        }
    }

    return { images, errors };
}
