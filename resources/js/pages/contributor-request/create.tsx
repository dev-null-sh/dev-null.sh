import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, HandHelping } from 'lucide-react';
import ContributorRequestController from '@/actions/App/Http/Controllers/ContributorRequestController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { contributorHelp } from '@/types/contributor-request';

const fieldClass =
    'mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-base text-foreground outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 sm:text-sm';

export default function Create() {
    return (
        <>
            <Head title="Post a contributor request" />
            <header className="flex h-14 items-center gap-3 border-b px-4 sm:px-6">
                <Link
                    href={ContributorRequestController.index()}
                    aria-label="Back to Help wanted"
                    className="rounded p-1 text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-5" />
                </Link>
                <h1 className="font-semibold">Post a contributor request</h1>
            </header>
            <section className="px-4 py-6 sm:px-6">
                <div className="mb-6 flex items-start gap-3">
                    <span className="rounded-lg border bg-muted p-2">
                        <HandHelping aria-hidden className="size-6" />
                    </span>
                    <div>
                        <h2 className="text-lg font-semibold">
                            A clear ask goes a long way.
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Tell developers what you’re building and how they
                            can help. Replies stay in the normal commit thread.
                        </p>
                    </div>
                </div>
                <Form
                    {...ContributorRequestController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div>
                                <label
                                    htmlFor="project-url"
                                    className="text-sm font-medium"
                                >
                                    GitHub repository or issue
                                </label>
                                <input
                                    id="project-url"
                                    name="project_url"
                                    type="url"
                                    required
                                    maxLength={255}
                                    placeholder="https://github.com/owner/project/issues/42"
                                    className={fieldClass}
                                    aria-invalid={!!errors.project_url}
                                    aria-describedby="project-help project-error"
                                />
                                <p
                                    id="project-help"
                                    className="mt-2 text-sm text-muted-foreground"
                                >
                                    Link a public repository or an issue. The
                                    link will also appear in your commit.
                                    Linking a project does not imply ownership.
                                </p>
                                <InputError
                                    id="project-error"
                                    message={errors.project_url}
                                />
                            </div>
                            <div>
                                <label
                                    htmlFor="request-kind"
                                    className="text-sm font-medium"
                                >
                                    What kind of help?
                                </label>
                                <select
                                    id="request-kind"
                                    name="kind"
                                    required
                                    defaultValue="testing"
                                    className={fieldClass}
                                    aria-invalid={!!errors.kind}
                                >
                                    {contributorHelp.map((kind) => (
                                        <option
                                            key={kind.value}
                                            value={kind.value}
                                        >
                                            {kind.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.kind} />
                            </div>
                            <div>
                                <label
                                    htmlFor="request-body"
                                    className="text-sm font-medium"
                                >
                                    What do you need?
                                </label>
                                <textarea
                                    id="request-body"
                                    name="body"
                                    required
                                    rows={6}
                                    maxLength={40000}
                                    placeholder="I’m releasing a CLI tool and need someone to test the Windows installer. A quick installation check and any error output would help."
                                    className={fieldClass}
                                    aria-invalid={!!errors.body}
                                    aria-describedby="request-body-help request-body-error"
                                />
                                <p
                                    id="request-body-help"
                                    className="mt-2 text-sm text-muted-foreground"
                                >
                                    Be specific about the task and what someone
                                    needs to get started. Normal commit limits
                                    and moderation apply. You can mark it filled
                                    later; the text cannot be edited.
                                </p>
                                <InputError
                                    id="request-body-error"
                                    message={errors.body}
                                />
                            </div>
                            <div className="flex flex-wrap gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    aria-busy={processing}
                                >
                                    {processing ? 'Posting…' : 'Post request'}
                                </Button>
                                <Button asChild variant="outline">
                                    <Link
                                        href={ContributorRequestController.index()}
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </section>
        </>
    );
}
Create.layout = AppLayout;
