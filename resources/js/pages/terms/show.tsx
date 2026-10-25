import { Head, Link } from '@inertiajs/react';
import AppController from '@/actions/App/Http/Controllers/AppController';
import PrivacyController from '@/actions/App/Http/Controllers/PrivacyController';
import {
    DocumentList,
    DocumentPage,
    DocumentSection,
} from '@/components/document-page';
import { WordmarkText } from '@/components/wordmark-text';
import AppLayout from '@/layouts/app-layout';
import { COMPANY_NAME, CONTACT_EMAIL, TERMS_EFFECTIVE_DATE } from '@/lib/legal';

function Mail() {
    return <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>;
}

function Privacy() {
    return <Link href={PrivacyController.show()}>privacy policy</Link>;
}

export default function Show() {
    return (
        <>
            <Head title="Terms" />

            <DocumentPage
                title="Terms of service"
                meta={`effective ${TERMS_EFFECTIVE_DATE}`}
            >
                <DocumentSection id="agreement" title="Agreement">
                    <p>
                        These terms are an agreement between you and{' '}
                        {COMPANY_NAME} ("we", "us"). The "service" means{' '}
                        <WordmarkText>/dev/null</WordmarkText> and everything we
                        offer under that name or any other, now or in the
                        future, including dev-null.sh, its apps, APIs, feeds,
                        tools, and features.
                    </p>
                    <p>
                        By using the service, you accept these terms and our{' '}
                        <Privacy />. If you don't accept them, don't use the
                        service.
                    </p>
                </DocumentSection>

                <DocumentSection id="changes" title="Changes">
                    <p>
                        We may change these terms at any time, for any reason.
                        Changes take effect as soon as they are posted on this
                        page. Using the service after that means you accept
                        them. It's your responsibility to check this page.
                    </p>
                </DocumentSection>

                <DocumentSection id="eligibility" title="Who can use it">
                    <p>
                        You must be at least 16 years old, sign in with your own
                        account, and be allowed to use the service under the
                        laws that apply to you, including sanctions and export
                        laws. If you use it for an organization, you accept
                        these terms on its behalf.
                    </p>
                </DocumentSection>

                <DocumentSection id="account" title="Your account">
                    <p>
                        You are responsible for everything that happens through
                        your account, including anything posted with your keys,
                        tools, or workflows, and for keeping them secure.
                    </p>
                </DocumentSection>

                <DocumentSection id="content" title="Your content">
                    <p>
                        You own what you post, and you are solely responsible
                        for it. You confirm you have the right to post it and
                        that it doesn't break the law or anyone's rights.
                    </p>
                    <p>
                        You grant us a worldwide, non-exclusive, royalty-free,
                        perpetual, transferable, and sublicensable license to
                        host, store, copy, process, analyze, modify, publish,
                        display, distribute, and otherwise use your content, in
                        any form and medium, to operate, improve, and promote
                        the service and to develop new features. Public content
                        can be seen, copied, and reused by anyone.
                    </p>
                    <p>
                        This license continues after you delete content or your
                        account, to the extent copies remain, such as in
                        replies, quotes, backups, caches, and copies made by
                        others. To the extent the law allows, you waive any
                        moral rights in your content against us and anyone we
                        license.
                    </p>
                    <p>
                        We have no obligation to store, keep, show, or back up
                        any content. If you send us feedback, we may use it
                        freely, without owing you anything.
                    </p>
                </DocumentSection>

                <DocumentSection id="our-property" title="Our property">
                    <p>
                        The service, including its name, design, software, and
                        everything in it that isn't user content, belongs to us
                        or our licensors. These terms only let you use the
                        service as we offer it, and grant you no other right.
                        Any API, feed, key, or tool we provide is part of the
                        service, may be limited, changed, or revoked at any
                        time, and may only be used as we allow.
                    </p>
                </DocumentSection>

                <DocumentSection id="apps" title="Apps">
                    <p>
                        Our apps, such as{' '}
                        <Link href={AppController.index()}>
                            /dev/null for Mac
                        </Link>
                        , are part of the service. We let you install and use
                        them on devices you own or control, only to use the
                        service, under a personal, non-exclusive,
                        non-transferable license that ends when your access to
                        the service ends. Get them only from dev-null.sh. Each
                        device you approve can act as you until you revoke it in
                        your settings, and you are responsible for what happens
                        through it. We may update, limit, or stop offering any
                        app, and an outdated version may stop working. Apps we
                        haven't had notarized by Apple ask you to approve them
                        in macOS before they first open, and you install them at
                        your own risk.
                    </p>
                </DocumentSection>

                <DocumentSection id="rules" title="Rules">
                    <p>Don't use the service to:</p>
                    <DocumentList
                        items={[
                            'Break the law, or post anything illegal.',
                            'Post sexual content involving minors. We report it to the authorities.',
                            'Threaten, harass, or abuse anyone, or post hateful, sexual, or violent content.',
                            'Post engagement bait: posts that fish for stars, replies, or follows instead of saying something.',
                            'Post low-effort content: one-word posts, the same post over and over, or copy-pasted memes.',
                            'Post AI-generated content as your own. Sharing code or output you got from a tool is fine if you say so.',
                            'Share other people’s personal information, impersonate anyone, or infringe anyone’s rights.',
                            'Spam, scam, manipulate the service, or use automated means we don’t provide.',
                            'Promote yourself by dropping links, referral codes, or the same launch across threads.',
                            'Scrape or collect data from the service, including to train AI models, other than through the public API within its limits.',
                            'Attack, probe, overload, or get around the security or limits of the service.',
                            'Do anything else we consider harmful to the service, its users, or us.',
                        ]}
                    />
                    <p>
                        To report content you believe is illegal or infringes
                        your rights, write to <Mail />. We decide how to handle
                        reports.
                    </p>
                </DocumentSection>

                <DocumentSection id="our-rights" title="Our rights">
                    <p>
                        At our sole discretion, at any time, for any reason or
                        no reason, and without notice, we may: remove or
                        restrict any content; suspend or delete any account;
                        change, limit, charge for, suspend, or discontinue any
                        part of the service, or all of it. We have no obligation
                        to monitor content, explain our decisions, or keep the
                        service available.
                    </p>
                    <p>
                        If you break these terms, we may stop you from posting
                        for a while (a timeout) or ban you. A ban applies to
                        your GitHub account, so you can't sign up again with it,
                        even after deleting your account. We keep a record of
                        timeouts and bans, and the GitHub account they apply to,
                        after your account is deleted.
                    </p>
                </DocumentSection>

                <DocumentSection
                    id="third-parties"
                    title="Third parties and AI"
                >
                    <p>
                        The service relies on, links to, and embeds services we
                        don't control, such as GitHub and AI providers. We are
                        not responsible for them, their availability, their
                        terms, or anything you do with them. The service uses
                        automated systems and AI, whose results may be wrong;
                        you rely on them at your own risk.
                    </p>
                    <p>
                        <WordmarkText>/dev/null</WordmarkText> is not affiliated
                        with or endorsed by GitHub, Inc. GitHub is a trademark
                        of GitHub, Inc.
                    </p>
                </DocumentSection>

                <DocumentSection id="disclaimer" title="No warranty">
                    <p className="font-medium uppercase">
                        The service is provided "as is" and "as available",
                        without any warranty of any kind, express or implied.
                        You use it entirely at your own risk. We don't guarantee
                        that it will be available, secure, accurate, or free of
                        errors, or that any content will be kept. All content is
                        the responsibility of whoever posted it; we don't
                        endorse it and are not responsible for it. Nothing in
                        the service is advice of any kind; don't rely on it. We
                        are not responsible for anything outside our reasonable
                        control, such as outages, attacks, or the acts of third
                        parties.
                    </p>
                </DocumentSection>

                <DocumentSection id="liability" title="Limitation of liability">
                    <p className="font-medium uppercase">
                        To the maximum extent permitted by law, {COMPANY_NAME}{' '}
                        and its owners, directors, employees, contractors,
                        affiliates, licensors, suppliers, and successors are not
                        liable for any loss or damage of any kind, direct or
                        indirect, arising out of or related to the service, its
                        content, its users, these terms, or your use of or
                        inability to use the service, under any legal theory,
                        even if we were advised of the possibility.
                    </p>
                    <p className="font-medium uppercase">
                        We will not pay any amount for any claim. If, despite
                        this, the law does not allow our liability to be
                        excluded entirely, it is limited to the lowest amount
                        the law allows.
                    </p>
                    <p className="font-medium uppercase">
                        You release us from any claim arising from disputes
                        between you and other users or third parties. To the
                        extent the law allows, any claim against us must be
                        brought within one year after it arises, or it is
                        permanently barred.
                    </p>
                    <p>
                        These limits apply to the maximum extent permitted by
                        law, and only exclude what the law allows to be
                        excluded.
                    </p>
                </DocumentSection>

                <DocumentSection id="indemnity" title="Indemnity">
                    <p>
                        You agree to defend and indemnify us, and our owners,
                        directors, employees, contractors, affiliates,
                        licensors, suppliers, and successors, against any claim,
                        loss, or cost, including legal fees, arising from your
                        content, your use of the service, or your breach of
                        these terms or the law, including claims by other users
                        or third parties. We may take over the defense of any
                        claim, at your expense, and you must cooperate.
                    </p>
                </DocumentSection>

                <DocumentSection id="termination" title="Termination">
                    <p>
                        You may stop using the service at any time. We may end
                        your access at any time, for any reason. The sections on
                        content, our property, our rights, third parties, no
                        warranty, limitation of liability, indemnity, law, and
                        general terms survive.
                    </p>
                </DocumentSection>

                <DocumentSection id="law" title="Law">
                    <p>
                        These terms are governed by the laws of Portugal. To the
                        extent the law allows, any dispute will be decided
                        exclusively by the courts of Lisbon, Portugal, and
                        claims may only be brought individually, not as part of
                        a class or representative action.
                    </p>
                </DocumentSection>

                <DocumentSection id="general" title="General">
                    <p>
                        These terms and the <Privacy /> are the entire agreement
                        between you and us. If any part is found unenforceable,
                        the rest still applies. Not enforcing a part is not a
                        waiver. We may transfer these terms to anyone; you may
                        not. These terms create no partnership, agency, or
                        employment, and give no rights to anyone other than you
                        and us. We may notify you through the service or by
                        email. Headings are for convenience only. If you are a
                        consumer, nothing here removes rights the law doesn't
                        allow to be removed. If a translation differs, the
                        English version applies.
                    </p>
                </DocumentSection>

                <DocumentSection id="contact" title="Contact">
                    <p>
                        {COMPANY_NAME}, <Mail />.
                    </p>
                </DocumentSection>
            </DocumentPage>
        </>
    );
}

Show.layout = AppLayout;
