import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { createDateTime } from '@/lib/date-time';

export function useDateTime() {
    const { auth } = usePage().props;
    const dateFormat = auth.dateTime?.dateFormat;
    const timeFormat = auth.dateTime?.timeFormat;
    return useMemo(
        () => createDateTime({ dateFormat, timeFormat }),
        [dateFormat, timeFormat],
    );
}
