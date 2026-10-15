import { Link } from '@inertiajs/react';
import TopicController from '@/actions/App/Http/Controllers/TopicController';

// Mirrors CreatePost::HASHTAG so every rendered link points at a topic that exists.
const hashtag =
    /(?<![\p{L}\p{N}_#])#((?=[\p{N}_]*\p{L})[\p{L}\p{N}_]{1,50})(?![\p{L}\p{N}_])/gu;

export function PostBody({ body }: { body: string }) {
    const parts: (string | { tag: string; name: string })[] = [];
    let last = 0;

    for (const match of body.matchAll(hashtag)) {
        parts.push(body.slice(last, match.index));
        parts.push({ tag: match[0], name: match[1].toLowerCase() });
        last = match.index + match[0].length;
    }

    parts.push(body.slice(last));

    return (
        <p className="mt-1 text-sm break-words whitespace-pre-wrap">
            {parts.map((part, index) =>
                typeof part === 'string' ? (
                    part
                ) : (
                    <Link
                        key={index}
                        href={TopicController.show(part.name)}
                        className="font-medium text-primary hover:underline"
                        data-test="topic-link"
                    >
                        {part.tag}
                    </Link>
                ),
            )}
        </p>
    );
}
