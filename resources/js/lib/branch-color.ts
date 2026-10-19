import type { CSSProperties } from "react";

/** Approximate luminance of the app’s dark surface (#111112). */
const DARK_SURFACE_LUMINANCE = 0.004;

/**
 * Relative luminance (0–1) for a #rrggbb colour.
 */
function luminance(hex: string): number | null {
    const match = /^#([0-9a-f]{6})$/i.exec(hex.trim());

    if (!match) {
        return null;
    }

    const channels = [0, 2, 4].map((offset) => {
        const channel =
            Number.parseInt(match[1].slice(offset, offset + 2), 16) / 255;

        return channel <= 0.03928
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

function contrastRatio(a: number, b: number): number {
    const lighter = Math.max(a, b);
    const darker = Math.min(a, b);

    return (lighter + 0.05) / (darker + 0.05);
}

/**
 * True when the brand colour is too dark to read as text on dark surfaces
 * (near-black brands like Vercel’s). Saturated colours like Laravel red stay false.
 */
export function isDarkBrandColor(color: string): boolean {
    const value = luminance(color);

    return value !== null && contrastRatio(value, DARK_SURFACE_LUMINANCE) < 3;
}

/**
 * Foreground for plain brand text (branch titles). Dark brands stay themselves in light
 * mode and flip to white in dark mode.
 */
export function branchTextColor(
    color: string | null | undefined,
): string | undefined {
    if (!color) {
        return undefined;
    }

    return isDarkBrandColor(color) ? `light-dark(${color}, #ffffff)` : color;
}

/**
 * Inline styles for a verified branch using its favicon brand colour.
 * Dark brands get a solid chip with white text so they stay readable in both themes.
 */
export function branchColorStyle(
    color: string | null | undefined,
): CSSProperties | undefined {
    if (!color) {
        return undefined;
    }

    if (isDarkBrandColor(color)) {
        return {
            color: "#ffffff",
            borderColor: `color-mix(in srgb, ${color} 65%, #ffffff)`,
            backgroundColor: color,
        };
    }

    return {
        color,
        borderColor: `color-mix(in srgb, ${color} 35%, transparent)`,
        backgroundColor: `color-mix(in srgb, ${color} 12%, transparent)`,
    };
}
