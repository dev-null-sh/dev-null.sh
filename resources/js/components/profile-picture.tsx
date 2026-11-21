import { useState } from 'react';
import { ImageViewer } from '@/components/image-viewer';
import { UserAvatar } from '@/components/user-avatar';
import { sizedAvatarUrl } from '@/lib/urls';
import type { Profile } from '@/types';

export function ProfilePicture({
    user,
}: {
    user: Pick<Profile, 'id' | 'name' | 'avatar_url'>;
}) {
    const [index, setIndex] = useState<number | null>(null);
    const avatar = (
        <UserAvatar
            user={user}
            className="size-20 text-2xl ring-[calc(2px/var(--profile-avatar-scale,1))] ring-(--lane) ring-offset-4 ring-offset-card"
            pixels={160}
        />
    );

    if (user.avatar_url === null) {
        return avatar;
    }

    return (
        <>
            <button
                type="button"
                aria-label={`View ${user.name}'s profile picture`}
                aria-haspopup="dialog"
                className="cursor-pointer rounded-full focus-visible:outline-2 focus-visible:outline-offset-8 focus-visible:outline-ring"
                onClick={() => setIndex(0)}
            >
                {avatar}
            </button>
            <ImageViewer
                images={[
                    {
                        id: user.id,
                        url: sizedAvatarUrl(user.avatar_url, 1024),
                        alt: `${user.name}'s profile picture`,
                        description: null,
                    },
                ]}
                index={index}
                onView={setIndex}
                onClose={() => setIndex(null)}
                showZoomButton={false}
                className="[&_img]:rounded-full"
            />
        </>
    );
}
