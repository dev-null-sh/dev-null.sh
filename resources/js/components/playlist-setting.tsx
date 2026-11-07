import { Form, router } from '@inertiajs/react';
import { Save, Unlink } from 'lucide-react';
import { toast } from 'sonner';
import UserPlaylistController from '@/actions/App/Http/Controllers/UserPlaylistController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export function PlaylistSetting({ url }: { url: string | null }) {
    const remove = () =>
        router.delete(UserPlaylistController.destroy.url(), {
            preserveScroll: true,
            optimistic: () => ({ playlistUrl: null }),
            onSuccess: () => router.flushAll(),
            onError: () =>
                toast.error(
                    'Could not remove your playlist. Please try again.',
                ),
        });

    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title="Winamp playlist"
                description="Share a public playlist or album from Audius. Anyone on a computer can play it in Winamp from your profile."
            />
            <Form
                key={url ?? ''}
                {...UserPlaylistController.update.form()}
                options={{ preserveScroll: true }}
                onSuccess={() => router.flushAll()}
                className="grid max-w-md gap-2"
            >
                {({ processing, errors }) => (
                    <>
                        <Label htmlFor="playlist-url">Audius link</Label>
                        <input
                            id="playlist-url"
                            type="url"
                            name="playlist_url"
                            required
                            defaultValue={url ?? ''}
                            placeholder="https://audius.co/…/playlist/…"
                            aria-invalid={Boolean(errors.playlist_url)}
                            className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                            data-test="playlist-url-input"
                        />
                        <div className="min-h-5">
                            <InputError message={errors.playlist_url} />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                                data-test="save-playlist-button"
                            >
                                {processing ? <Spinner /> : <Save />}
                                Save
                            </Button>
                            {url !== null && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={remove}
                                    data-test="remove-playlist-button"
                                >
                                    <Unlink />
                                    Remove
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}
