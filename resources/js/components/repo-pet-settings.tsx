import { router, useForm, useHttp } from '@inertiajs/react';
import { Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import {
    destroy,
    update,
} from '@/actions/App/Http/Controllers/RepoPetController';
import { show } from '@/actions/App/Http/Controllers/RepoPetRepositoryController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { RepoPet, RepoPetCreature } from '@/components/repo-pet';
import type { RepoPetConfiguration, RepoPetData } from '@/components/repo-pet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type RepositoryWorkflows = {
    repository: string;
    repository_id: number;
    workflows: { id: number; name: string }[];
};

export default function RepoPetSettings({
    configuration,
    pet,
}: {
    configuration: RepoPetConfiguration | null;
    pet: RepoPetData | null;
}) {
    const form = useForm({
        repository: configuration?.repository ?? '',
        workflow_id: 0,
    });
    const removal = useForm({});
    const lookup = useHttp<Record<string, never>, RepositoryWorkflows>({});
    const { cancel: cancelLookup } = lookup;
    const requestId = useRef(0);
    const [repository, setRepository] = useState<RepositoryWorkflows | null>(
        null,
    );
    const [isLookingUp, setIsLookingUp] = useState(false);
    const [lookupError, setLookupError] = useState('');
    const [saveError, setSaveError] = useState('');
    const removalError = Object.values(removal.errors).find(
        (error): error is string => typeof error === 'string',
    );
    const isSaving = form.processing || removal.processing;
    const canSave =
        repository?.repository === form.data.repository &&
        repository.workflows.some(
            (workflow) => workflow.id === form.data.workflow_id,
        );

    useEffect(
        () => () => {
            requestId.current += 1;
            cancelLookup();
        },
        [cancelLookup],
    );

    const changeRepository = (value: string) => {
        requestId.current += 1;
        cancelLookup();
        setIsLookingUp(false);
        setRepository(null);
        setLookupError('');
        setSaveError('');
        form.clearErrors();
        form.setData({ repository: value, workflow_id: 0 });
    };

    const findWorkflows = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const currentRequest = ++requestId.current;
        cancelLookup();
        setRepository(null);
        setLookupError('');
        setSaveError('');
        setIsLookingUp(true);
        form.clearErrors();
        form.setData('workflow_id', 0);

        try {
            const result = await lookup.get(
                show.url({
                    query: { repository: form.data.repository.trim() },
                }),
                {
                    onError: (errors) => {
                        if (currentRequest === requestId.current) {
                            setLookupError(
                                errors.repository ??
                                    'This repository could not be checked.',
                            );
                        }
                    },
                },
            );

            if (currentRequest === requestId.current && result) {
                setRepository(result);
                form.setData({ repository: result.repository, workflow_id: 0 });
            }
        } catch {
            if (currentRequest === requestId.current) {
                setLookupError(
                    'Could not check GitHub right now. Please try again.',
                );
            }
        } finally {
            if (currentRequest === requestId.current) {
                setIsLookingUp(false);
            }
        }
    };

    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!canSave) {
            return;
        }

        setSaveError('');
        form.submit(update(), {
            preserveScroll: true,
            onSuccess: () => router.flushAll(),
            onNetworkError: () => {
                setSaveError('Could not save your pet. Please try again.');
                return false;
            },
        });
    };

    const remove = () => {
        setSaveError('');
        removal.submit(destroy(), {
            preserveScroll: true,
            onSuccess: () => router.flushAll(),
            onNetworkError: () => {
                setSaveError('Could not remove your pet. Please try again.');
                return false;
            },
        });
    };

    return (
        <section className="px-4 py-6 sm:px-6" aria-label="Repo pet settings">
            <Heading
                title="Repo pet"
                description="A small creature with a strong opinion about your build. Pick a public repository and one GitHub Actions workflow on its default branch."
            />

            {pet ? (
                <RepoPet pet={pet} />
            ) : (
                <div className="flex items-center gap-3 rounded-lg border bg-muted/20 p-3">
                    <RepoPetCreature mood="sleeping" className="w-24" />
                    <div>
                        <p className="text-sm font-medium">
                            Currently between repositories.
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Choose a workflow. Meet your pet.
                        </p>
                    </div>
                </div>
            )}

            <form onSubmit={findWorkflows} className="mt-5 space-y-2">
                <Label htmlFor="repo-pet-repository">
                    Public GitHub repository
                </Label>
                <div className="flex flex-wrap gap-2">
                    <Input
                        id="repo-pet-repository"
                        value={form.data.repository}
                        onChange={(event) =>
                            changeRepository(event.target.value)
                        }
                        placeholder="owner/repo"
                        autoComplete="off"
                        spellCheck={false}
                        disabled={isSaving}
                        aria-invalid={Boolean(
                            lookupError || form.errors.repository,
                        )}
                        aria-describedby="repo-pet-repository-error"
                        className="min-w-40 flex-1 font-mono text-sm"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={
                            isSaving ||
                            isLookingUp ||
                            form.data.repository.trim() === ''
                        }
                    >
                        {isLookingUp ? <Spinner /> : <Search />}
                        {isLookingUp ? 'Checking…' : 'Find workflows'}
                    </Button>
                </div>
                <InputError
                    id="repo-pet-repository-error"
                    message={lookupError || form.errors.repository}
                    role="alert"
                />
            </form>

            {repository &&
                (repository.workflows.length > 0 ? (
                    <form onSubmit={save} className="mt-4 space-y-2">
                        <Label htmlFor="repo-pet-workflow">Workflow</Label>
                        <Select
                            value={
                                form.data.workflow_id
                                    ? String(form.data.workflow_id)
                                    : ''
                            }
                            onValueChange={(value) => {
                                form.clearErrors('workflow_id');
                                setSaveError('');
                                form.setData('workflow_id', Number(value));
                            }}
                            disabled={isSaving}
                        >
                            <SelectTrigger
                                id="repo-pet-workflow"
                                className="w-full"
                                aria-invalid={Boolean(form.errors.workflow_id)}
                                aria-describedby="repo-pet-workflow-error"
                            >
                                <SelectValue placeholder="Choose a workflow" />
                            </SelectTrigger>
                            <SelectContent>
                                {repository.workflows.map((workflow) => (
                                    <SelectItem
                                        key={workflow.id}
                                        value={String(workflow.id)}
                                    >
                                        {workflow.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            id="repo-pet-workflow-error"
                            message={form.errors.workflow_id}
                            role="alert"
                        />
                        {repository.workflows.length === 100 && (
                            <p className="text-xs text-muted-foreground">
                                Showing up to 100 active workflows.
                            </p>
                        )}
                        <Button
                            type="submit"
                            disabled={isSaving || !canSave}
                            className="mt-3"
                        >
                            {form.processing && <Spinner />}
                            {configuration ? 'Save pet' : 'Adopt pet'}
                        </Button>
                    </form>
                ) : (
                    <p
                        className="mt-3 text-sm text-muted-foreground"
                        role="status"
                    >
                        No active GitHub Actions workflows found in this
                        repository.
                    </p>
                ))}

            <InputError
                message={saveError || removalError}
                className="mt-3"
                role="alert"
            />
            {configuration && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={isSaving || isLookingUp}
                    onClick={remove}
                    className="mt-4 -ml-2 text-muted-foreground hover:text-destructive"
                >
                    {removal.processing ? <Spinner /> : <Trash2 />}
                    Remove pet
                </Button>
            )}
        </section>
    );
}
