/**
 * Diagram colors follow resources/css/app.css:
 * blue is the site's primary action, green/purple are existing lane colors.
 * Distinct fills are intentionally muted, with readable labels in both modes.
 */
export type DiagramTheme = 'light' | 'dark';

const palettes = {
    dark: {
        canvas: '#18181a',
        text: '#ececec',
        primary: '#202b40',
        primaryBorder: '#7aa2f7',
        secondary: '#1d3026',
        secondaryBorder: '#51cf66',
        tertiary: '#30243c',
        tertiaryBorder: '#da77f2',
        line: '#91a2b9',
        note: '#382d1c',
        noteBorder: '#caa34a',
        noteText: '#f2e6ca',
        pie: [
            '#7aa2f7',
            '#51cf66',
            '#da77f2',
            '#ff8a4c',
            '#3bc9db',
            '#f06595',
            '#fcc419',
        ],
    },
    light: {
        canvas: '#ffffff',
        text: '#121417',
        primary: '#e8effc',
        primaryBorder: '#3b6fe0',
        secondary: '#e7f5eb',
        secondaryBorder: '#2f9e44',
        tertiary: '#f3eafa',
        tertiaryBorder: '#9c36b5',
        line: '#718199',
        note: '#fff4dc',
        noteBorder: '#b7791f',
        noteText: '#513c1d',
        pie: [
            '#3b6fe0',
            '#2f9e44',
            '#9c36b5',
            '#d9480f',
            '#0c8599',
            '#c2255c',
            '#b7791f',
        ],
    },
} as const;

export function mermaidTheme(theme: DiagramTheme) {
    const palette = palettes[theme];

    return {
        darkMode: theme === 'dark',
        background: palette.canvas,
        mainBkg: palette.primary,
        primaryColor: palette.primary,
        primaryTextColor: palette.text,
        primaryBorderColor: palette.primaryBorder,
        secondaryColor: palette.secondary,
        secondaryTextColor: palette.text,
        secondaryBorderColor: palette.secondaryBorder,
        tertiaryColor: palette.tertiary,
        tertiaryTextColor: palette.text,
        tertiaryBorderColor: palette.tertiaryBorder,
        textColor: palette.text,
        lineColor: palette.line,
        nodeBorder: palette.primaryBorder,
        nodeTextColor: palette.text,
        clusterBkg: palette.tertiary,
        clusterBorder: palette.tertiaryBorder,
        edgeLabelBackground: palette.canvas,
        noteBkgColor: palette.note,
        noteBorderColor: palette.noteBorder,
        noteTextColor: palette.noteText,
        actorBkg: palette.primary,
        actorBorder: palette.primaryBorder,
        actorTextColor: palette.text,
        actorLineColor: palette.line,
        signalColor: palette.line,
        signalTextColor: palette.text,
        activationBkgColor: palette.secondary,
        activationBorderColor: palette.secondaryBorder,
        // SVGs shown via <img> cannot rely on the page's Geist @font-face.
        // Use the same system font for Mermaid's layout measurements and image paint.
        fontFamily: 'system-ui, sans-serif',
        fontSize: '14px',
        pie1: palette.pie[0],
        pie2: palette.pie[1],
        pie3: palette.pie[2],
        pie4: palette.pie[3],
        pie5: palette.pie[4],
        pie6: palette.pie[5],
        pie7: palette.pie[6],
    };
}
