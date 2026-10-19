import PasskeyOptionsController from '@/actions/App/Http/Controllers/PasskeyOptionsController';
import SigningChallengeController from '@/actions/App/Http/Controllers/SigningChallengeController';

export type PasskeySignature = {
    nonce: string;
    credential_id: string;
    client_data_json: string;
    authenticator_data: string;
    signature: string;
};

export type NewPasskey = {
    credential_id: string;
    client_data_json: string;
    authenticator_data: string;
    public_key: string;
    algorithm: number;
};

export type CommitDraft = {
    body?: string | null;
    command?: string | null;
    parent_id?: string | null;
    quoted_commit_id?: string | null;
    scheduled_at?: string | null;
    poll?: { options: string[]; duration: number | string } | null;
    images?: File[];
};

type Challenge = {
    nonce: string;
    options: {
        challenge: string;
        rpId: string;
        allowCredentials: { type: 'public-key'; id: string }[];
        userVerification: UserVerificationRequirement;
        timeout: number;
    };
};

type CreationOptions = {
    challenge: string;
    rp: PublicKeyCredentialRpEntity;
    user: { id: string; name: string; displayName: string };
    pubKeyCredParams: PublicKeyCredentialParameters[];
    excludeCredentials: { type: 'public-key'; id: string }[];
    authenticatorSelection: AuthenticatorSelectionCriteria;
    attestation: AttestationConveyancePreference;
    timeout: number;
};

export class PasskeyError extends Error {}

export function supportsPasskeys(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.PublicKeyCredential !== undefined
    );
}

export async function signCommit(
    draft: CommitDraft,
): Promise<PasskeySignature> {
    const challenge = await postJson<Challenge>(
        SigningChallengeController.store().url,
        {
            body: draft.body ?? '',
            command: draft.command ?? null,
            parent_id: draft.parent_id ?? null,
            quoted_commit_id: draft.quoted_commit_id ?? null,
            scheduled_at: draft.scheduled_at ?? null,
            poll: draft.poll
                ? {
                      options: draft.poll.options,
                      duration: Number(draft.poll.duration),
                  }
                : null,
            image_hashes: await Promise.all(
                (draft.images ?? []).map((image) => sha256(image)),
            ),
        },
    );

    return sign(challenge);
}

export async function confirmSigning(
    action: string,
): Promise<PasskeySignature> {
    return sign(
        await postJson<Challenge>(SigningChallengeController.store().url, {
            action,
        }),
    );
}

export async function createPasskey(): Promise<NewPasskey> {
    const options = await postJson<CreationOptions>(
        PasskeyOptionsController.store().url,
        {},
    );

    const credential = await navigator.credentials.create({
        publicKey: {
            ...options,
            challenge: decode(options.challenge),
            user: { ...options.user, id: decode(options.user.id) },
            excludeCredentials: options.excludeCredentials.map((item) => ({
                ...item,
                id: decode(item.id),
            })),
        },
    });

    if (!(credential instanceof PublicKeyCredential)) {
        throw new PasskeyError('No passkey was created.');
    }

    const response = credential.response as AuthenticatorAttestationResponse;
    const publicKey = response.getPublicKey();

    if (publicKey === null) {
        throw new PasskeyError(
            'Your passkey provider did not share the public key, so /dev/null cannot check its signatures.',
        );
    }

    return {
        credential_id: encode(credential.rawId),
        client_data_json: encode(response.clientDataJSON),
        authenticator_data: encode(response.getAuthenticatorData()),
        public_key: encode(publicKey),
        algorithm: response.getPublicKeyAlgorithm(),
    };
}

async function sign(challenge: Challenge): Promise<PasskeySignature> {
    const credential = await navigator.credentials.get({
        publicKey: {
            ...challenge.options,
            challenge: decode(challenge.options.challenge),
            allowCredentials: challenge.options.allowCredentials.map(
                (item) => ({ ...item, id: decode(item.id) }),
            ),
        },
    });

    if (!(credential instanceof PublicKeyCredential)) {
        throw new PasskeyError('The passkey did not sign.');
    }

    const response = credential.response as AuthenticatorAssertionResponse;

    return {
        nonce: challenge.nonce,
        credential_id: encode(credential.rawId),
        client_data_json: encode(response.clientDataJSON),
        authenticator_data: encode(response.authenticatorData),
        signature: encode(response.signature),
    };
}

async function postJson<T>(url: string, body: object): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const data = (await response.json().catch(() => null)) as {
            message?: string;
        } | null;

        throw new PasskeyError(data?.message ?? 'Could not reach /dev/null.');
    }

    return (await response.json()) as T;
}

async function sha256(file: File): Promise<string> {
    const digest = await crypto.subtle.digest(
        'SHA-256',
        await file.arrayBuffer(),
    );

    return Array.from(new Uint8Array(digest), (byte) =>
        byte.toString(16).padStart(2, '0'),
    ).join('');
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function encode(buffer: ArrayBuffer): string {
    return btoa(String.fromCharCode(...new Uint8Array(buffer)))
        .replaceAll('+', '-')
        .replaceAll('/', '_')
        .replace(/=+$/, '');
}

function decode(value: string): ArrayBuffer {
    const base64 = value.replaceAll('-', '+').replaceAll('_', '/');
    const binary = atob(base64.padEnd(Math.ceil(base64.length / 4) * 4, '='));

    return Uint8Array.from(binary, (char) => char.charCodeAt(0)).buffer;
}
