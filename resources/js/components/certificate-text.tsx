import { certificateHashPattern } from '@/lib/certificate-hashes';

export function CertificateText({ text }: { text: string }) {
    return text.split(certificateHashPattern).map((part, index) =>
        index % 2 === 0 ? (
            part
        ) : (
            <span key={index} className="certificate-hash">
                <span>{part}</span>
            </span>
        ),
    );
}
