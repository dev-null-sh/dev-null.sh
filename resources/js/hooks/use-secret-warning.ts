import { useMemo, useRef, useState } from 'react';
import { findSecrets } from '@/lib/sensitive-text';

export function useSecretWarning(body: string) {
    const findings = useMemo(() => findSecrets(body), [body]);
    const [confirming, setConfirming] = useState(false);
    const approvedBody = useRef<string | null>(null);

    const beforeSubmit = () => {
        if (findings.length === 0 || approvedBody.current === body) {
            approvedBody.current = null;
            return true;
        }

        setConfirming(true);
        return false;
    };

    const approve = () => {
        approvedBody.current = body;
        setConfirming(false);
    };

    return {
        findings,
        confirming,
        setConfirming,
        beforeSubmit,
        approve,
    };
}
