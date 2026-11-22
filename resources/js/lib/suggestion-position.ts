export function suggestionPosition(
    character: { left: number; top: number; bottom: number },
    viewport: { left: number; top: number; width: number; height: number },
    layoutHeight: number,
) {
    const margin = 8;
    const gap = 6;
    const width = Math.min(288, Math.max(0, viewport.width - margin * 2));
    const left = Math.max(
        viewport.left + margin,
        Math.min(
            character.left - 12,
            viewport.left + viewport.width - width - margin,
        ),
    );
    const above = Math.max(0, character.top - viewport.top - margin - gap);
    const below = Math.max(
        0,
        viewport.top + viewport.height - margin - character.bottom - gap,
    );
    const opensAbove = below < 320 && above > below;

    return {
        left,
        width,
        maxHeight: Math.min(320, opensAbove ? above : below),
        ...(opensAbove
            ? { bottom: layoutHeight - character.top + gap }
            : { top: character.bottom + gap }),
    };
}

export type SuggestionPosition = ReturnType<typeof suggestionPosition>;

// Every style that changes where text wraps, so the mirror below wraps exactly like the textarea.
const mirroredProperties = [
    'box-sizing',
    'width',
    'border-top-width',
    'border-right-width',
    'border-bottom-width',
    'border-left-width',
    'padding-top',
    'padding-right',
    'padding-bottom',
    'padding-left',
    'font-family',
    'font-size',
    'font-weight',
    'font-style',
    'font-stretch',
    'font-feature-settings',
    'font-variant',
    'line-height',
    'letter-spacing',
    'word-spacing',
    'text-transform',
    'text-indent',
    'tab-size',
];

/**
 * Where a character of the textarea is drawn, measured on a hidden copy of it that wraps the same way.
 */
function characterRect(textarea: HTMLTextAreaElement, index: number) {
    const style = window.getComputedStyle(textarea);
    const mirror = document.createElement('div');
    const marker = document.createElement('span');

    mirroredProperties.forEach((property) =>
        mirror.style.setProperty(property, style.getPropertyValue(property)),
    );
    mirror.style.position = 'absolute';
    mirror.style.visibility = 'hidden';
    mirror.style.top = '0';
    mirror.style.left = '-9999px';
    mirror.style.whiteSpace = 'pre-wrap';
    mirror.style.overflowWrap = 'break-word';

    mirror.textContent = textarea.value.slice(0, index);
    marker.textContent = textarea.value.slice(index) || '.';
    mirror.appendChild(marker);
    document.body.appendChild(mirror);

    const rect = textarea.getBoundingClientRect();
    const lineHeight =
        Number.parseFloat(style.lineHeight) ||
        Number.parseFloat(style.fontSize) * 1.4;
    const top = rect.top + marker.offsetTop - textarea.scrollTop;
    const left = rect.left + marker.offsetLeft - textarea.scrollLeft;

    mirror.remove();

    return { top, left, bottom: top + lineHeight };
}

/**
 * Where suggestions for the text at a character of the textarea float: right under it, or above it near the bottom of
 * the visible viewport.
 */
export function suggestionPositionAt(
    textarea: HTMLTextAreaElement,
    index: number,
): SuggestionPosition {
    const character = characterRect(textarea, index);
    const viewport = window.visualViewport;

    return suggestionPosition(
        character,
        {
            left: viewport?.offsetLeft ?? 0,
            top: viewport?.offsetTop ?? 0,
            width: viewport?.width ?? window.innerWidth,
            height: viewport?.height ?? window.innerHeight,
        },
        window.innerHeight,
    );
}
