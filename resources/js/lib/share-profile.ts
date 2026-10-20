/**
 * The text, when given, goes before the link, both in the share sheet and in what is copied.
 */
export type ProfileShareData = { title: string; url: string; text?: string };

export async function shareProfile(
    data: ProfileShareData,
    share: ((data: ProfileShareData) => Promise<void>) | undefined,
    copy: (url: string) => Promise<boolean>,
): Promise<'shared' | 'copied' | 'cancelled' | 'failed'> {
    if (share) {
        try {
            await share(data);

            return 'shared';
        } catch (error) {
            if (error instanceof Error && error.name === 'AbortError') {
                return 'cancelled';
            }
        }
    }

    try {
        const copied =
            data.text === undefined ? data.url : `${data.text} ${data.url}`;

        return (await copy(copied)) ? 'copied' : 'failed';
    } catch {
        return 'failed';
    }
}
