import { useLang } from '@erag/lang-sync-inertia/react';
import { Head } from '@inertiajs/react';
import { CopyableCode } from '@/components/copyable-code';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const workflow = [
    'name: /dev/null',
    '',
    'on:',
    '  release:',
    '    types: [published]',
    '',
    'permissions: {}',
    '',
    'jobs:',
    '  commit:',
    '    runs-on: ubuntu-latest',
    '    steps:',
    '      - uses: nunomaduro/dev-null.sh/.github/actions/commit@production',
    '        with:',
    '          body: |',
    '            Released ${{ github.event.release.name }}.',
    '            ${{ github.event.release.html_url }}',
    '          ssh-key: ${{ secrets.DEVNULL_SSH_KEY }}',
].join('\n');

export default function Show({ url }: { url: string }) {
    const { __ } = useLang();
    const inputs = [
        { name: 'body', description: __('settings.terminal.inputs.body') },
        {
            name: 'ssh-key',
            description: __('settings.terminal.inputs.ssh_key'),
        },
        {
            name: 'reply-to',
            description: __('settings.terminal.inputs.reply_to'),
        },
    ];

    return (
        <>
            <Head title={__('settings.terminal.title')} />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title={__('settings.terminal.from_terminal')}
                    description={__(
                        'settings.terminal.from_terminal_description',
                    )}
                />

                <div className="space-y-3">
                    <CopyableCode
                        label={__('settings.terminal.command')}
                        code={`curl -fsSL ${url}/commit | bash -s -- 'It was DNS.'`}
                    />
                    <CopyableCode
                        label={__('settings.terminal.reply_command')}
                        code={`curl -fsSL ${url}/commit | bash -s -- --reply 0be0130 'Agreed!'`}
                    />
                </div>
            </section>

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title={__('settings.terminal.from_actions')}
                    description={__(
                        'settings.terminal.from_actions_description',
                    )}
                />

                <ol className="space-y-4 text-sm">
                    <li className="space-y-2">
                        <p>
                            <StepNumber number={1} />
                            {__('settings.terminal.step_generate')}
                        </p>
                        <CopyableCode
                            label={__('settings.terminal.key_command')}
                            code="ssh-keygen -t ed25519 -N '' -C devnull-ci -f devnull-ci"
                        />
                    </li>
                    <li>
                        <p className="max-w-prose text-pretty">
                            <StepNumber number={2} />
                            {__('settings.terminal.step_add_key_before')}{' '}
                            <code className="font-mono text-[13px]">
                                devnull-ci.pub
                            </code>{' '}
                            {__('settings.terminal.step_add_key_middle')}{' '}
                            <a
                                href="https://github.com/settings/ssh/new"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="underline decoration-muted-foreground/50 underline-offset-4 hover:decoration-foreground"
                            >
                                {__('settings.terminal.step_add_key_link')}
                            </a>
                            {__('settings.terminal.step_add_key_after')}
                        </p>
                    </li>
                    <li>
                        <p className="max-w-prose text-pretty">
                            <StepNumber number={3} />
                            {__(
                                'settings.terminal.step_save_secret_before',
                            )}{' '}
                            <code className="font-mono text-[13px]">
                                devnull-ci
                            </code>
                            {__('settings.terminal.step_save_secret_middle')}{' '}
                            <code className="font-mono text-[13px]">
                                DEVNULL_SSH_KEY
                            </code>
                            {__('settings.terminal.step_save_secret_after')}
                        </p>
                    </li>
                    <li className="space-y-2">
                        <p>
                            <StepNumber number={4} />
                            {__(
                                'settings.terminal.step_add_workflow_before',
                            )}{' '}
                            <code className="font-mono text-[13px]">
                                .github/workflows/devnull.yml
                            </code>
                            {__('settings.terminal.step_add_workflow_after')}
                        </p>
                        <CopyableCode
                            label={__('settings.terminal.workflow')}
                            language="yaml"
                            code={workflow}
                        />
                    </li>
                </ol>

                <dl className="mt-5 divide-y rounded-lg border text-sm">
                    {inputs.map((input) => (
                        <div
                            key={input.name}
                            className="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 px-4 py-2.5"
                        >
                            <dt className="font-mono text-[13px] text-muted-foreground">
                                {input.name}
                            </dt>
                            <dd className="text-pretty">{input.description}</dd>
                        </div>
                    ))}
                </dl>

                <p className="mt-4 max-w-prose text-sm text-pretty text-muted-foreground">
                    {__('settings.terminal.job_output')}
                </p>
            </section>
        </>
    );
}

function StepNumber({ number }: { number: number }) {
    return (
        <span className="mr-2 font-mono text-muted-foreground">{number}.</span>
    );
}

Show.layout = [AppLayout, SettingsLayout];
