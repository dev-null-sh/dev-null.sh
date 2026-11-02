import { Head } from '@inertiajs/react';
import { DocumentPage } from '@/components/document-page';
import { TermsOfService } from '@/components/terms-of-service';
import AppLayout from '@/layouts/app-layout';
import { TERMS_EFFECTIVE_DATE } from '@/lib/legal';

export default function Show() {
    return (
        <>
            <Head title="Terms" />

            <DocumentPage
                title="Terms of service"
                meta={`effective ${TERMS_EFFECTIVE_DATE}`}
            >
                <TermsOfService />
            </DocumentPage>
        </>
    );
}

Show.layout = AppLayout;
