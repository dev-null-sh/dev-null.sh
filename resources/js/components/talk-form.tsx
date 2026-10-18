import { Form } from '@inertiajs/react';
import TalkController from '@/actions/App/Http/Controllers/TalkController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const fieldClassName =
    'block w-full rounded-md border bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50 md:text-[15px]';

export function TalkForm({ onCancel }: { onCancel?: () => void }) {
    return (
        <section className="border-b px-5 py-5 sm:px-6">
            <p className="text-sm leading-relaxed text-muted-foreground">
                Add a talk with a recording, slides, or both. It publishes to
                your profile and the talks explorer.
            </p>

            <Form
                {...TalkController.store.form()}
                className="mt-5 space-y-4"
                onSuccess={() => onCancel?.()}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="space-y-2">
                            <Label htmlFor="title">Title</Label>
                            <input
                                id="title"
                                name="title"
                                required
                                maxLength={200}
                                className={fieldClassName}
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <textarea
                                id="description"
                                name="description"
                                required
                                maxLength={4000}
                                rows={4}
                                className={`${fieldClassName} min-h-24 resize-y`}
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="presented_on">Date</Label>
                                <input
                                    id="presented_on"
                                    name="presented_on"
                                    type="date"
                                    required
                                    className={fieldClassName}
                                />
                                <InputError message={errors.presented_on} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="event_name">
                                    Event name (optional)
                                </Label>
                                <input
                                    id="event_name"
                                    name="event_name"
                                    maxLength={200}
                                    className={fieldClassName}
                                />
                                <InputError message={errors.event_name} />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="recording_url">Recording URL</Label>
                            <input
                                id="recording_url"
                                name="recording_url"
                                type="url"
                                placeholder="https://youtube.com/…"
                                maxLength={2048}
                                className={fieldClassName}
                            />
                            <InputError message={errors.recording_url} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="slides_url">Slides URL</Label>
                            <input
                                id="slides_url"
                                name="slides_url"
                                type="url"
                                placeholder="https://speakerdeck.com/…"
                                maxLength={2048}
                                className={fieldClassName}
                            />
                            <InputError message={errors.slides_url} />
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" disabled={processing}>
                                Publish talk
                            </Button>
                            {onCancel && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={onCancel}
                                >
                                    Cancel
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}
