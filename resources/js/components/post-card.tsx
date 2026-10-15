import { Link, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import PostController from '@/actions/App/Http/Controllers/PostController';
import { PostBody } from '@/components/post-body';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { formatRelativeTime } from '@/lib/utils';
import { show } from '@/routes/user';
import type { Post } from '@/types';

export function PostCard({ post }: { post: Post }) {
    const { auth } = usePage().props;

    return (
        <article
            className="flex gap-3 border-b border-sidebar-border/70 px-4 py-4 dark:border-sidebar-border"
            data-test="post"
        >
            <Link href={show(post.user)} className="shrink-0">
                <UserAvatar user={post.user} className="size-10" />
            </Link>

            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1 text-sm">
                    <Link
                        href={show(post.user)}
                        className="truncate font-semibold hover:underline"
                    >
                        {post.user.name}
                    </Link>
                    <span className="truncate text-muted-foreground">
                        @{post.user.username}
                    </span>
                    <span className="text-muted-foreground">·</span>
                    <time
                        dateTime={post.created_at}
                        title={new Date(post.created_at).toLocaleString()}
                        className="shrink-0 text-muted-foreground"
                    >
                        {formatRelativeTime(post.created_at)}
                    </time>

                    {post.user_id === auth.user.id && (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="ml-auto size-7 text-muted-foreground hover:text-destructive"
                            asChild
                        >
                            <Link
                                href={PostController.destroy(post)}
                                as="button"
                                preserveScroll
                                onBefore={() =>
                                    confirm(
                                        'Are you sure you want to delete this post?',
                                    )
                                }
                                aria-label="Delete post"
                                data-test="delete-post-button"
                            >
                                <Trash2 />
                            </Link>
                        </Button>
                    )}
                </div>

                <PostBody body={post.body} />
            </div>
        </article>
    );
}
