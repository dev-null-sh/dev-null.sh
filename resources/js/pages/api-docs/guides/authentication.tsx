import { ExampleTabs } from '@/components/api-docs/example-tabs';
import {
    Code,
    FieldList,
    Guide,
    GuideSection,
    Snippet,
} from '@/components/api-docs/guide';
import type { ApiDocsExample, ApiDocsSnippet } from '@/types';

export function Authentication({
    signing,
    snippets,
}: {
    signing: ApiDocsExample[];
    snippets: Record<string, ApiDocsSnippet>;
}) {
    return (
        <Guide>
            <GuideSection title="Public or signed">
                <FieldList
                    fields={[
                        {
                            name: 'public',
                            children:
                                'No signing headers. Public profiles, approved commits and organizations, as a guest sees them on the web.',
                        },
                        {
                            name: 'signed',
                            children:
                                'Everything else, as the web shows it to the person who approved the key: their pending commits, their notifications, their remotes. A public endpoint answers a signed request that way too.',
                        },
                    ]}
                />
                <p>Each endpoint of the reference says which it is.</p>
            </GuideSection>

            <GuideSection title="Getting a key approved" meta="once per device">
                <p>
                    Your app keeps its own SSH key, made for it. It doesn't need
                    to be on anyone's GitHub account, and a key that is only on
                    GitHub can't sign API requests.
                </p>
                <Snippet snippet={snippets.keygen} />
                <p>
                    Then open <Code>https://dev-null.sh/mobile/authorize</Code>{' '}
                    in a browser, the way an OAuth app would, with PKCE:
                </p>
                <FieldList
                    fields={[
                        {
                            name: 'fingerprint',
                            children: (
                                <>
                                    The key's, as <Code>ssh-keygen -lf</Code>{' '}
                                    prints it: <Code>SHA256:…</Code>
                                </>
                            ),
                        },
                        {
                            name: 'device_name',
                            children:
                                'Where the key lives, shown on the approval page',
                        },
                        {
                            name: 'app_name',
                            children:
                                'Optional. Your app, so people can tell apps apart in their devices',
                        },
                        {
                            name: 'app_id',
                            children: (
                                <>
                                    Optional. A stable platform and identifier,
                                    like <Code>macos:sh.dev-null.mac</Code>
                                </>
                            ),
                        },
                        {
                            name: 'code_challenge',
                            children: (
                                <>
                                    The SHA-256 of your verifier, base64url
                                    without padding, 43 characters, with{' '}
                                    <Code>code_challenge_method=S256</Code>
                                </>
                            ),
                        },
                        {
                            name: 'state',
                            children: 'Anything, sent back as it is',
                        },
                        {
                            name: 'redirect_uri',
                            children: (
                                <>
                                    Where the code goes:{' '}
                                    <Code>http://127.0.0.1:&lt;port&gt;</Code>{' '}
                                    your app listens on. <Code>devnull://</Code>{' '}
                                    is the /dev/null apps' own.
                                </>
                            ),
                        },
                    ]}
                />
                <p>
                    The person signs in with GitHub if they need to, checks the
                    fingerprint and approves. The browser then goes to your{' '}
                    <Code>redirect_uri</Code> with a <Code>code</Code> and your{' '}
                    <Code>state</Code>, or with <Code>error=access_denied</Code>
                    . Redeem the code with <Code>POST /devices</Code>, signed
                    with the new key, its body{' '}
                    <Code>{'{"code": "…", "code_verifier": "…"}'}</Code>. The
                    key is now one of their devices, until they revoke it in
                    Settings → Devices.
                </p>
                <p>
                    Only signing in uses <Code>POST /devices</Code>, so the
                    reference leaves it out with the other endpoints only the
                    apps need to work.
                </p>
            </GuideSection>

            <GuideSection title="Signing a request">
                <p>
                    Every request signs these five lines with the key, as an SSH
                    signature in the <Code>devnull-api</Code> namespace:
                </p>
                <Snippet snippet={snippets.message} />
                <FieldList
                    keyWidth="sm:grid-cols-[11rem_minmax(0,1fr)]"
                    fields={[
                        {
                            name: 'X-Devnull-Timestamp',
                            children:
                                'The timestamp you signed, within five minutes of the server’s clock',
                        },
                        {
                            name: 'X-Devnull-Nonce',
                            children:
                                '32 lowercase hex digits, good for one request only',
                        },
                        {
                            name: 'X-SSH-Signature',
                            children:
                                'The signature, its armor lines dropped and the rest on one line',
                        },
                    ]}
                />
                <p>
                    The path and query are exactly the ones you request, from
                    the root: <Code>/api/v1/search/commits?q=laravel</Code>. The
                    body is exactly the bytes you send, as JSON with{' '}
                    <Code>Content-Type: application/json</Code>, or nothing when
                    there is none.
                </p>
                <p>
                    Every signed example of the reference calls one of these,
                    with its method, path and body:
                </p>
                <ExampleTabs examples={signing} />
                <p>
                    A request with images or a voice note is multipart, with
                    nothing else in it: the JSON as a <Code>payload</Code> file
                    part of type <Code>application/json</Code> (with curl,{' '}
                    <Code>-F 'payload=@body.json;type=application/json'</Code>
                    ), and either the images in <Code>images[]</Code> or the
                    voice note in <Code>voice</Code>, never both. The signed
                    message then has one more line before the body, the SHA-256
                    of each file, in hex, comma separated in the order they are
                    attached, and the namespace is{' '}
                    <Code>devnull-api-images</Code>.
                </p>
            </GuideSection>

            <GuideSection title="Before the first commit">
                <p>
                    Someone has to accept the commit rules once, in the web
                    composer, before an app can commit for them. Until then,
                    commits answer <Code>commit_rules_required</Code>.
                </p>
            </GuideSection>
        </Guide>
    );
}
