export type MentionFragment = { start: number; end: number; query: string };

export function activeMention(
    value: string,
    start: number,
    end = start,
): MentionFragment | null {
    if (start !== end) return null;

    const match = /(?:^|[\s([{])@([\p{L}\p{N}-]{0,100})$/u.exec(
        value.slice(0, start),
    );
    if (!match) return null;

    const position = start - match[1].length - 1;
    const token = value.slice(0, position).split(/\s/u).at(-1) ?? '';
    if (/[:/@]/u.test(token)) return null;

    const suffix = /^[\p{L}\p{N}-]*/u.exec(value.slice(start))?.[0] ?? '';
    return { start: position, end: start + suffix.length, query: match[1] };
}

export function insertMention(
    value: string,
    fragment: MentionFragment,
    username: string,
    maxLength: number,
): { value: string; caret: number } | null {
    const suffix = value.slice(fragment.end);
    const replacement = `@${username}${/^\s/u.test(suffix) ? '' : ' '}`;
    const next = value.slice(0, fragment.start) + replacement + suffix;
    if (next.length > maxLength) return null;

    return {
        value: next,
        caret:
            fragment.start + replacement.length + (/^\s/u.test(suffix) ? 1 : 0),
    };
}

// Mirror the textarea's wrapping and scroll position to locate its caret.
export function caretRect(textarea: HTMLTextAreaElement): DOMRect {
    const mirror = document.createElement('div');
    const style = getComputedStyle(textarea);
    mirror.style.cssText = style.cssText;
    for (const property of style) {
        mirror.style.setProperty(property, style.getPropertyValue(property));
    }
    Object.assign(mirror.style, {
        position: 'fixed',
        visibility: 'hidden',
        pointerEvents: 'none',
        left: '0',
        top: '0',
        width: `${textarea.getBoundingClientRect().width}px`,
        height: 'auto',
        minHeight: '0',
        fieldSizing: 'fixed',
        whiteSpace: 'pre-wrap',
        overflowWrap: 'break-word',
    });
    mirror.textContent = textarea.value.slice(0, textarea.selectionStart);
    const marker = document.createElement('span');
    marker.textContent = '\u200b';
    mirror.append(marker);
    document.body.append(mirror);
    const markerRect = marker.getBoundingClientRect();
    const rect = textarea.getBoundingClientRect();
    const lineHeight = parseFloat(style.lineHeight) || 24;
    const result = new DOMRect(
        rect.left + markerRect.left - textarea.scrollLeft,
        Math.max(
            rect.top,
            Math.min(
                rect.bottom - lineHeight,
                rect.top + markerRect.top - textarea.scrollTop,
            ),
        ),
        0,
        lineHeight,
    );
    mirror.remove();
    return result;
}
