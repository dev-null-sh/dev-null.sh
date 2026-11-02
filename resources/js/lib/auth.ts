import { trackSignInTap } from '@/lib/analytics';
import { login } from '@/routes';

/**
 * Guests read commits and profiles like everyone else, yet anything they would do, like starring or replying, takes
 * them to sign in with GitHub first. They come back to the page they were reading. It is a full page visit, as
 * GitHub is on another origin.
 */
export function signIn(): void {
    trackSignInTap();
    window.location.assign(login().url);
}
