import { Head } from '@inertiajs/react';
import { CopyableCode } from '@/components/copyable-code';
import Heading from '@/components/heading';
import { WordmarkText } from '@/components/wordmark-text';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

export default function Show({ url }: { url: string }) {
    return (
        <>
            <Head title="Terminal settings" />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Commit from your terminal"
                    description={
                        <WordmarkText>
                            No login, no token: the script signs your commit
                            with an SSH key that is on your GitHub account, and
                            /dev/null checks it against the keys GitHub
                            publishes for you.
                        </WordmarkText>
                    }
                />

                <div className="space-y-3">
                    <CopyableCode
                        label="command"
                        code={`curl -fsSL ${url}/commit | bash -s -- 'It was DNS.'`}
                    />
                    <CopyableCode
                        label="reply command"
                        code={`curl -fsSL ${url}/commit | bash -s -- --reply 0be0130 'Agreed!'`}
                    />
                    <CopyableCode
                        label="image command"
                        code={`curl -fsSL ${url}/commit | bash -s -- --image shipped.png 'Shipped!'`}
                    />
                </div>
            </section>
        </>
    );
}

Show.layout = [AppLayout, SettingsLayout];
