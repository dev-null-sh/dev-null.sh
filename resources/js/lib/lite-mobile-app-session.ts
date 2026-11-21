/**
 * Whether a response says the web view's session or its CSRF token has ended. Only the site's answers count: another
 * site's 401 says nothing about this session.
 */
export function isRefusedBySession(
    status: number,
    url: string,
    origin: string,
): boolean {
    return (
        (status === 401 || status === 419) &&
        new URL(url, origin).origin === origin
    );
}
