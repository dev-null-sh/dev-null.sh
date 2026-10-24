import type { ReactNode } from 'react';
import { useState } from 'react';
import DeviceActivationController from '@/actions/App/Http/Controllers/DeviceActivationController';
import { AuthorizationPage } from '@/components/authorization-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { useUser } from '@/hooks/use-user';
import { formatUserCode } from '@/lib/user-code';
import { laneColor } from '@/lib/utils';

type Authorization = {
    user_code: string;
    device_name: string;
    fingerprint: string;
};

type Done = {
    status: 'approved' | 'denied';
    device_name: string;
};

type Props =
    | { authorization: Authorization; csrfToken: string }
    | { done: Done }
    | { error?: string; userCode?: string };

export default function Show(props: Props) {
    if ('authorization' in props) {
        return <Approve {...props} />;
    }

    if ('done' in props) {
        return <Finished {...props.done} />;
    }

    return <Enter {...props} />;
}

function Enter({ error, userCode }: { error?: string; userCode?: string }) {
    const { user } = useUser();
    const [code, setCode] = useState(formatUserCode(userCode ?? ''));

    return (
        <AuthorizationPage
            title="Sign in a terminal"
            lane={laneColor(user.lane)}
            command="devnull login"
        >
            <h1 className="mt-4 text-[clamp(1.75rem,4vw,2.5rem)] leading-tight font-semibold tracking-tight text-balance">
                Enter the code your terminal shows.
            </h1>
            <p className="mt-3 max-w-[48ch] text-lg text-pretty text-muted-foreground">
                A terminal or an agent signing in to /dev/null as you shows a
                code like WDJB-MJHT. Type it here, then check which device it is
                before approving it.
            </p>

            {/* A plain form, like the mobile page's: a code that can't be found comes back as this page... */}
            <form
                {...DeviceActivationController.show.form()}
                className="mt-8 flex max-w-md flex-wrap items-start gap-2"
            >
                <div className="min-w-0 flex-1">
                    <label htmlFor="user-code" className="sr-only">
                        Code
                    </label>
                    <input
                        id="user-code"
                        name="user_code"
                        type="text"
                        value={code}
                        onChange={(event) =>
                            setCode(formatUserCode(event.target.value))
                        }
                        required
                        autoFocus
                        autoComplete="one-time-code"
                        autoCapitalize="characters"
                        spellCheck={false}
                        maxLength={9}
                        placeholder="WDJB-MJHT"
                        aria-invalid={error ? true : undefined}
                        aria-describedby={error ? 'user-code-error' : undefined}
                        className="h-11 w-full rounded-lg border bg-background px-3 font-mono text-lg tracking-[0.2em] uppercase outline-none placeholder:text-muted-foreground focus:border-ring"
                    />
                    <InputError
                        id="user-code-error"
                        message={error}
                        className="mt-2"
                    />
                </div>
                <Button type="submit" size="lg" className="min-w-28">
                    Continue
                </Button>
            </form>
        </AuthorizationPage>
    );
}

function Approve({
    authorization,
    csrfToken,
}: {
    authorization: Authorization;
    csrfToken: string;
}) {
    const { user } = useUser();
    const [isDeciding, setIsDeciding] = useState(false);

    return (
        <AuthorizationPage
            title={`Approve ${authorization.device_name}`}
            lane={laneColor(user.lane)}
            command="git credential approve"
        >
            <h1 className="mt-4 text-[clamp(1.75rem,4vw,2.5rem)] leading-tight font-semibold tracking-tight text-balance [overflow-wrap:anywhere]">
                Approve {authorization.device_name}?
            </h1>
            <p className="mt-3 max-w-[48ch] text-lg text-pretty text-muted-foreground">
                A device calling itself {authorization.device_name} asks to sign
                in as you, with the code below. Only approve it if you started
                this yourself a moment ago.
            </p>

            <dl className="mt-6 grid max-w-md gap-4">
                <div>
                    <dt className="text-sm text-muted-foreground">Code</dt>
                    <dd className="mt-1.5 font-mono text-lg tracking-[0.2em]">
                        {authorization.user_code}
                    </dd>
                </div>
                <div>
                    <dt className="text-sm text-muted-foreground">
                        Signs in as
                    </dt>
                    <dd className="mt-1.5 flex min-w-0 items-center gap-3">
                        <UserAvatar user={user} className="size-10" />
                        <span className="min-w-0">
                            <span
                                className="block truncate font-semibold"
                                title={user.name}
                            >
                                {user.name}
                            </span>
                            <span
                                className="block truncate font-mono text-[13px] text-muted-foreground"
                                title={`@${user.username}`}
                            >
                                @{user.username}
                            </span>
                        </span>
                    </dd>
                </div>
                <div>
                    <dt className="text-sm text-muted-foreground">With key</dt>
                    <dd className="mt-1.5 rounded-md border bg-muted/50 px-3 py-2 font-mono text-[13px] [overflow-wrap:anywhere]">
                        {authorization.fingerprint}
                    </dd>
                </div>
            </dl>

            <p className="mt-6 max-w-[48ch] text-pretty text-muted-foreground">
                The device will be able to use /dev/null as you. You can revoke
                it any time under Settings → Devices.
            </p>

            {/* Plain forms, as on the mobile page: the answer is this page again, never a redirect. One form per
                decision, since a submit button disabled while the page is sent no longer carries its own value... */}
            <div className="mt-8 flex flex-wrap gap-2">
                <DecisionForm
                    decision="approved"
                    userCode={authorization.user_code}
                    csrfToken={csrfToken}
                    disabled={isDeciding}
                    onSubmit={() => setIsDeciding(true)}
                >
                    <Button
                        type="submit"
                        size="lg"
                        disabled={isDeciding}
                        className="min-w-28"
                    >
                        Approve
                    </Button>
                </DecisionForm>
                <DecisionForm
                    decision="denied"
                    userCode={authorization.user_code}
                    csrfToken={csrfToken}
                    disabled={isDeciding}
                    onSubmit={() => setIsDeciding(true)}
                >
                    <Button
                        type="submit"
                        variant="outline"
                        size="lg"
                        disabled={isDeciding}
                    >
                        Deny
                    </Button>
                </DecisionForm>
            </div>
        </AuthorizationPage>
    );
}

function DecisionForm({
    decision,
    userCode,
    csrfToken,
    disabled,
    onSubmit,
    children,
}: {
    decision: Done['status'];
    userCode: string;
    csrfToken: string;
    disabled: boolean;
    onSubmit: () => void;
    children: ReactNode;
}) {
    return (
        <form
            {...DeviceActivationController.store.form()}
            onSubmit={(event) => {
                if (disabled) {
                    event.preventDefault();

                    return;
                }

                onSubmit();
            }}
        >
            <input type="hidden" name="_token" value={csrfToken} />
            <input type="hidden" name="user_code" value={userCode} />
            <input type="hidden" name="decision" value={decision} />
            {children}
        </form>
    );
}

function Finished({ status, device_name }: Done) {
    const { user } = useUser();
    const approved = status === 'approved';

    return (
        <AuthorizationPage
            title={approved ? 'Device approved' : 'Device denied'}
            lane={approved ? laneColor(user.lane) : 'var(--rail)'}
            command={
                approved ? 'git credential approve' : 'git credential reject'
            }
        >
            <h1 className="mt-4 text-[clamp(1.75rem,4vw,2.5rem)] leading-tight font-semibold tracking-tight text-balance [overflow-wrap:anywhere]">
                {approved
                    ? `${device_name} is signed in.`
                    : `${device_name} was denied.`}
            </h1>
            <p className="mt-3 max-w-[48ch] text-lg text-pretty text-muted-foreground">
                {approved
                    ? 'Your terminal signs in on its own in a few seconds. You can close this tab, and revoke the device any time under Settings → Devices.'
                    : 'Nothing was approved. Your terminal is told so on its next try. You can close this tab.'}
            </p>
        </AuthorizationPage>
    );
}
