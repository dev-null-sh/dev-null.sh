import { useMemo, useRef, useState } from 'react';
import { findSecrets } from '@/lib/sensitive-text';

export function useSecretWarning(body: string | string[]) {
    const findingsByPart = useMemo(
        () => (typeof body === 'string' ? [body] : body).map(findSecrets),
        [body],
    );
    const findings = findingsByPart.flat();
    const snapshot = JSON.stringify(body);
    const [confirming, setConfirming] = useState(false);
    const approvedBody = useRef<string | null>(null);

    const beforeSubmit = () => {
        if (findings.length === 0 || approvedBody.current === snapshot) {
            approvedBody.current = null;
            return true;
        }

        setConfirming(true);
        return false;
    };

    const approve = () => {
        approvedBody.current = snapshot;
        setConfirming(false);
    };

    return {
        findings,
        findingsByPart,
        confirming,
        setConfirming,
        beforeSubmit,
        approve,
    };
}
