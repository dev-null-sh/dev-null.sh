/**
 * Anything inside a commit that does its own thing when clicked, so the click is left to it.
 */
const interactiveSelector = [
    'a',
    'button',
    'input',
    'textarea',
    'select',
    'label',
    'summary',
    'iframe',
    'video',
    'audio',
    '[contenteditable]',
    '[role="button"]',
    '[role="link"]',
    '[role="checkbox"]',
    '[role="radio"]',
    '[role="menuitem"]',
].join(', ');

export type CommitClick = {
    button: number;
    metaKey: boolean;
    ctrlKey: boolean;
    defaultPrevented: boolean;
    target: EventTarget | null;
    currentTarget: { contains(node: Node | null): boolean };
};

type ClosestTarget = Node & { closest(selector: string): HTMLElement | null };

/**
 * Where a click on the bare surface of a commit, or of a reply inside it, should take you: the innermost card's
 * permalink, in a new tab for a middle or modified click. Clicks on its links and controls, clicks that end a text
 * selection, clicks from portals (menus, tooltips) and clicks on the commit you are already looking at go nowhere.
 */
export function clickedCommit(
    event: CommitClick,
    selection: string,
    currentPath: string,
): { url: string; newTab: boolean } | null {
    const target = event.target as ClosestTarget | null;

    if (
        event.defaultPrevented ||
        event.button > 1 ||
        selection !== '' ||
        typeof target?.closest !== 'function' ||
        !event.currentTarget.contains(target)
    ) {
        return null;
    }

    const control = target.closest(interactiveSelector);

    if (control && event.currentTarget.contains(control)) {
        return null;
    }

    const url = target.closest('[data-reply], [data-commit]')?.dataset
        .permalink;

    if (!url || url === currentPath) {
        return null;
    }

    return {
        url,
        newTab: event.button === 1 || event.metaKey || event.ctrlKey,
    };
}
