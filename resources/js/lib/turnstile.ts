type TurnstileOptions = {
    sitekey: string;
    size: 'flexible';
    theme: 'light' | 'dark';
    callback: (token: string) => void;
    'expired-callback': () => void;
    'error-callback': () => void;
};

export type Turnstile = {
    render: (container: HTMLElement, options: TurnstileOptions) => string;
    remove: (widgetId: string) => void;
};

declare global {
    interface Window {
        turnstile?: Turnstile;
    }
}

const scriptUrl =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let loading: Promise<Turnstile> | null = null;

export function loadTurnstile(): Promise<Turnstile> {
    loading ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = scriptUrl;
        script.onload = () => {
            if (window.turnstile === undefined) {
                reject(new Error(`Turnstile is missing from ${scriptUrl}.`));

                return;
            }

            resolve(window.turnstile);
        };
        script.onerror = () => {
            loading = null;
            script.remove();
            reject(new Error(`Turnstile failed to load from ${scriptUrl}.`));
        };
        document.head.append(script);
    });

    return loading;
}
