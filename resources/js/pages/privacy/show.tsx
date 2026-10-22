import { Head, Link } from '@inertiajs/react';
import TermsController from '@/actions/App/Http/Controllers/TermsController';
import {
    DocumentList,
    DocumentPage,
    DocumentSection,
} from '@/components/document-page';
import AppLayout from '@/layouts/app-layout';
import {
    COMPANY_NAME,
    CONTACT_EMAIL,
    PRIVACY_EFFECTIVE_DATE,
} from '@/lib/legal';
import { edit as profileSettings } from '@/routes/user-profile';

function Mail() {
    return <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>;
}

function Terms() {
    return <Link href={TermsController.show()}>terms of service</Link>;
}

export default function Show() {
    return (
        <>
            <Head title="Privacy" />

            <DocumentPage
                title="Privacy policy"
                meta={`effective ${PRIVACY_EFFECTIVE_DATE}`}
            >
                <DocumentSection id="who" title="Who we are">
                    <p>
                        {COMPANY_NAME}, a company registered in Portugal ("we",
                        "us"), operates /dev/null and everything we offer under
                        that name or any other, now or in the future (the
                        "service"), and is the controller of the personal data
                        processed in it. Contact: <Mail />.
                    </p>
                </DocumentSection>

                <DocumentSection id="collect" title="What we collect">
                    <DocumentList
                        items={[
                            <>
                                <strong>From GitHub, when you sign in:</strong>{' '}
                                your GitHub ID, username, name, avatar, bio,
                                company, location, website, the email address
                                GitHub shares, whether you have a Sponsors
                                listing, your public SSH key fingerprints, and
                                who you follow and who follows you.
                            </>,
                            <>
                                <strong>
                                    From the commit script, when you run it:
                                </strong>{' '}
                                the public SSH keys in your SSH agent and{' '}
                                <code>~/.ssh</code>, without their comments, to
                                find the one on your GitHub account. They are
                                only compared in memory against the fingerprints
                                we store, and are never stored or logged.
                            </>,
                            <>
                                <strong>What you post and do:</strong> commits,
                                replies, quotes, images, polls, votes, stars,
                                follows, mutes, blocks, reports, settings, and
                                any other content or activity in the service,
                                messages you send us, and data derived from all
                                of this, such as rankings and moderation
                                signals.
                            </>,
                            <>
                                <strong>Private messages:</strong> messages,
                                images, and voice notes you send in Remotes,
                                which only the people in the conversation can
                                see. They are encrypted at rest, are not used by
                                AI features, and are erased with your account.
                                Voice notes are recorded with your microphone
                                only after you start recording; recording stops
                                and the microphone is released when you stop or
                                discard.
                            </>,
                            <>
                                <strong>From others:</strong> what other users
                                post, say, or report about you, such as replies,
                                quotes, and mentions.
                            </>,
                            <>
                                <strong>Technical data:</strong> IP address,
                                browser and device information, logs, and how
                                you use the service, such as the last day you
                                used it.
                            </>,
                            <>
                                <strong>Cookies and browser storage</strong>{' '}
                                needed for the service to work, such as for
                                sign-in, security, and preferences.
                            </>,
                        ]}
                    />
                    <p>
                        We may also process any other data you give us, that
                        others give us about you, or that is generated when you
                        use the service. New features may involve other data,
                        which this policy covers once it is updated to describe
                        it. Signing in with GitHub is required to use the
                        service.
                    </p>
                </DocumentSection>

                <DocumentSection id="public" title="Public content">
                    <p className="font-medium">
                        Your profile and what you post are public, to anyone on
                        the internet, including through the public API, feeds,
                        search engines, and other sites. Anyone can copy,
                        archive, or reuse public content, and we can't control
                        or remove those copies. Deleted content may still appear
                        in replies, quotes, search engines, and copies others
                        made. Private messages are not public. Don't post
                        anything you don't want public. You are responsible for
                        any personal data of others that you post.
                    </p>
                </DocumentSection>

                <DocumentSection id="use" title="How we use it">
                    <DocumentList
                        items={[
                            'To provide and operate the service (performance of our contract with you, GDPR art. 6(1)(b)).',
                            'To moderate content, prevent abuse, keep the service secure, enforce our terms, improve the service, and develop new features (our legitimate interests in running a safe and working service, art. 6(1)(f)).',
                            'To communicate with you about the service (art. 6(1)(b) and (f)).',
                            'To comply with the law, and to establish, exercise, or defend legal claims (art. 6(1)(c) and (f)).',
                            'For anything else you consent to; you can withdraw consent at any time (art. 6(1)(a)).',
                        ]}
                    />
                    <p>
                        We may use aggregated or de-identified data for any
                        purpose. We don't sell personal data.
                    </p>
                </DocumentSection>

                <DocumentSection id="ai" title="Automated processing and AI">
                    <p>
                        Content, including text, images, and search queries, is
                        processed by automated systems and AI providers,
                        including OpenAI, OpenCode, and DeepSeek, to moderate it
                        before it is published, and to power search, rankings,
                        and other features. These systems only decide how
                        content appears in the service, and don't produce legal
                        or similarly significant effects on you.
                    </p>
                    <p>
                        Tagging our AI bot in a public commit asks it to reply
                        publicly. The tagged text, a limited window of public
                        replies leading to it, and relevant public posts and
                        knowledge-base excerpts are sent through OpenCode to
                        DeepSeek. Search terms are processed by OpenAI. Private
                        messages are not included in Tom's context.
                    </p>
                    <p>
                        Public post text is also sent through OpenCode to
                        DeepSeek to detect its language and, when requested, to
                        translate it. Identical text shares cached translations
                        for up to seven days. Your browser's preferred language
                        selects which translation to display. Private messages
                        are not translated.
                    </p>
                </DocumentSection>

                <DocumentSection id="share" title="Who we share it with">
                    <DocumentList
                        items={[
                            'Service providers that process data for us, such as hosting, storage, network, and AI providers, and GitHub for sign-in.',
                            'Moderators and others who help us run the service.',
                            'Third parties whose content you load, such as GitHub for avatars and the providers of embedded players once you press play, under their own privacy policies, for which we are not responsible.',
                            'Authorities and others, when required by law or when we believe it is necessary to protect the service, its users, us, or anyone else.',
                            'A buyer, successor, or other party in a merger, acquisition, financing, reorganization, or sale of assets.',
                            'Professional advisers, such as lawyers, accountants, and insurers.',
                            'Anyone you ask us to share it with, or consent to.',
                        ]}
                    />
                    <p>
                        The service is operated from Portugal. Wherever you are,
                        your data is processed in the European Economic Area and
                        in the countries where our providers operate. These
                        recipients may process data outside the European
                        Economic Area, including in the United States, under
                        safeguards the law accepts, such as adequacy decisions
                        or standard contractual clauses, a copy of which you can
                        request from us.
                    </p>
                </DocumentSection>

                <DocumentSection id="retention" title="How long we keep it">
                    <p>
                        For as long as your account exists, and afterwards for
                        as long as needed for the purposes above, such as
                        keeping threads intact, preventing abuse, complying with
                        the law, and defending legal claims. Deleted content may
                        remain in backups and logs for a period.
                    </p>
                    <p>
                        If a moderator put you in timeout or banned you, we keep
                        a record of it after you delete your account: your
                        GitHub account id, the reason, and when it happened. It
                        stops a banned GitHub account from signing up again, and
                        keeps a timeout from ending early by signing up again.
                    </p>
                </DocumentSection>

                <DocumentSection id="rights" title="Your rights">
                    <p>
                        You can export your data or delete your account from{' '}
                        <Link href={profileSettings()}>settings</Link>. Under
                        the GDPR, you may also have the right to access,
                        correct, erase, restrict, or port your data, to object
                        to its processing, and to lodge a complaint with a data
                        protection authority, such as Portugal's CNPD. You may
                        object at any time to processing based on our legitimate
                        interests. To exercise these rights, write to <Mail />.
                        We may ask you to verify your identity, and may refuse
                        or charge for requests the law allows us to refuse, such
                        as ones that are manifestly unfounded or excessive.
                    </p>
                </DocumentSection>

                <DocumentSection id="security" title="Security">
                    <p>
                        We take reasonable measures to protect data, but no
                        system is secure, and we don't guarantee the security of
                        any data. You are responsible for keeping your GitHub
                        account and any keys secure.
                    </p>
                </DocumentSection>

                <DocumentSection id="age" title="Age">
                    <p>
                        The service is not for anyone under 16. We may delete
                        any account we believe belongs to someone younger.
                    </p>
                </DocumentSection>

                <DocumentSection id="changes" title="Changes">
                    <p>
                        We may change this policy at any time. Changes take
                        effect as soon as they are posted on this page. It's
                        your responsibility to check this page.
                    </p>
                </DocumentSection>

                <DocumentSection id="contact" title="Contact">
                    <p>
                        {COMPANY_NAME}, <Mail />. See also our <Terms />.
                    </p>
                </DocumentSection>
            </DocumentPage>
        </>
    );
}

Show.layout = AppLayout;
