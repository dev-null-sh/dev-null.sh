export type CardClick = {
    button: number;
    metaKey: boolean;
    ctrlKey: boolean;
    isOnCard: boolean;
};

/**
 * What a click on a commit or reply card does: open it in place, open it in a
 * new tab on a Cmd/Ctrl-click or middle-click like a regular link, or nothing.
 */
export function cardClickAction(click: CardClick): 'visit' | 'new-tab' | null {
    if (!click.isOnCard) {
        return null;
    }

    if (click.metaKey || click.ctrlKey || click.button === 1) {
        return 'new-tab';
    }

    return click.button === 0 ? 'visit' : null;
}
