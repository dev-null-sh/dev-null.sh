import { useEffect, useRef, useState } from 'react';
import CommitVideoController from '@/actions/App/Http/Controllers/CommitVideoController';
import CommitVideoProcessingController from '@/actions/App/Http/Controllers/CommitVideoProcessingController';
import type { CommitVideo } from '@/types';

function headers(): Record<string, string> {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice(11);
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
    };
}

async function request(
    { url, method }: { url: string; method: string },
    body?: object,
) {
    const response = await fetch(url, {
        method,
        headers: headers(),
        ...(body && { body: JSON.stringify(body) }),
    });
    if (!response.ok)
        throw new Error(
            'Could not prepare this video. Remove it and try again.',
        );
    return response.json();
}

export function useCommitVideo() {
    const [video, setVideo] = useState<CommitVideo | null>(null);
    const [file, setFile] = useState<File | null>(null);
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState<string | null>(null);
    const [description, setDescription] = useState('');
    const [aspectRatio, setAspectRatio] = useState(16 / 9);
    const generation = useRef(0);
    const owned = useRef<string | null>(null);
    const xhr = useRef<XMLHttpRequest | null>(null);
    const cancel = () => {
        generation.current++;
        xhr.current?.abort();
        const id = owned.current;
        owned.current = null;
        if (id) void request(CommitVideoController.destroy(id)).catch(() => {});
    };
    useEffect(() => () => cancel(), []);
    const remove = () => {
        cancel();
        setVideo(null);
        setFile(null);
        setError(null);
        setProgress(0);
        setDescription('');
    };
    const attached = () => {
        owned.current = null;
        remove();
    };
    const pick = async (selected: File) => {
        remove();
        if (
            !selected.type.startsWith('video/') ||
            selected.size === 0 ||
            selected.size > 100 * 1024 * 1024
        ) {
            setError('Choose one video up to 60 seconds and 100 MB.');
            return;
        }
        setFile(selected);
        const current = generation.current;
        try {
            const duration = await new Promise<number>((resolve, reject) => {
                const preview = document.createElement('video');
                const url = URL.createObjectURL(selected);
                const finish = () => {
                    URL.revokeObjectURL(url);
                    preview.removeAttribute('src');
                    preview.load();
                };
                preview.preload = 'metadata';
                preview.onloadedmetadata = () => {
                    const seconds = preview.duration;
                    if (
                        current === generation.current &&
                        preview.videoHeight > 0
                    )
                        setAspectRatio(
                            preview.videoWidth / preview.videoHeight,
                        );
                    finish();
                    resolve(seconds);
                };
                preview.onerror = () => {
                    finish();
                    reject(
                        new Error(
                            'This video could not be read. Choose another file.',
                        ),
                    );
                };
                preview.src = url;
            });
            if (current !== generation.current) return;
            if (!Number.isFinite(duration) || duration <= 0 || duration > 60)
                throw new Error('Choose a video no longer than 60 seconds.');
            const result = await request(CommitVideoController.store(), {
                size: selected.size,
            });
            if (current !== generation.current) {
                void request(
                    CommitVideoController.destroy(result.video.id),
                ).catch(() => {});
                return;
            }
            owned.current = result.video.id;
            setVideo(result.video);
            await new Promise<void>((resolve, reject) => {
                const upload = new XMLHttpRequest();
                xhr.current = upload;
                upload.open(result.upload.method, result.upload.url);
                for (const [key, value] of Object.entries(
                    result.upload.headers as Record<string, string | string[]>,
                )) {
                    upload.setRequestHeader(
                        key,
                        Array.isArray(value) ? value.join(', ') : value,
                    );
                }
                if (
                    new URL(result.upload.url, window.location.href).origin ===
                    window.location.origin
                ) {
                    const csrf = headers()['X-XSRF-TOKEN'];
                    if (csrf) upload.setRequestHeader('X-XSRF-TOKEN', csrf);
                }
                upload.upload.onprogress = (event) => {
                    if (
                        event.lengthComputable &&
                        current === generation.current
                    )
                        setProgress(
                            Math.round((event.loaded / event.total) * 100),
                        );
                };
                upload.onload = () =>
                    upload.status >= 200 && upload.status < 300
                        ? resolve()
                        : reject(
                              new Error(
                                  'Video upload failed. Remove it and try again.',
                              ),
                          );
                upload.onerror = () =>
                    reject(
                        new Error(
                            'Video upload failed. Check your connection and try again.',
                        ),
                    );
                upload.onabort = () => reject(new Error('Upload cancelled.'));
                upload.send(selected);
            });
            if (current !== generation.current) return;
            let resultVideo = (
                await request(
                    CommitVideoProcessingController.store(result.video.id),
                    {},
                )
            ).video as CommitVideo;
            while (current === generation.current) {
                if (resultVideo.status === 'ready' && resultVideo.poster_url) {
                    const poster = new Image();
                    poster.src = resultVideo.poster_url;
                    await poster.decode().catch(() => {});
                    if (current !== generation.current) return;
                }
                setVideo(resultVideo);
                if (resultVideo.status === 'failed')
                    throw new Error(
                        'Video processing failed. Remove it and choose another video.',
                    );
                if (resultVideo.status === 'ready') break;
                await new Promise((resolve) => setTimeout(resolve, 2000));
                if (current !== generation.current) return;
                resultVideo = (
                    await request(CommitVideoController.show(resultVideo.id))
                ).video;
            }
        } catch (failure) {
            if (current === generation.current)
                setError(
                    failure instanceof Error
                        ? failure.message
                        : 'Could not upload video.',
                );
        }
    };
    return {
        video,
        file,
        progress,
        error,
        description,
        aspectRatio,
        setDescription,
        pick,
        remove,
        attached,
        ready: video?.status === 'ready' && !error,
    };
}
