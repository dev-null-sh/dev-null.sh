// The board of a /minesweeper commit. Keep in sync with app/Services/Minesweeper.php.
export const rows = 7;
export const columns = 16;
export const mineCount = 16;
export const cellCount = rows * columns;

export function neighbors(cell: number): number[] {
    const row = Math.floor(cell / columns);
    const column = cell % columns;
    const result: number[] = [];

    for (const rowOffset of [-1, 0, 1]) {
        for (const columnOffset of [-1, 0, 1]) {
            const neighborRow = row + rowOffset;
            const neighborColumn = column + columnOffset;

            if (
                (rowOffset !== 0 || columnOffset !== 0) &&
                neighborRow >= 0 &&
                neighborRow < rows &&
                neighborColumn >= 0 &&
                neighborColumn < columns
            ) {
                result.push(neighborRow * columns + neighborColumn);
            }
        }
    }

    return result;
}

/**
 * Clicking a revealed number with as many flags around it as mines reveals the rest of its neighbors, like the
 * classic game. Anything else reveals nothing.
 */
export function chord(
    cell: number,
    cells: (number | null)[],
    flags: ReadonlySet<number>,
): number[] {
    const count = cells[cell];

    if (count === null || count === 0) {
        return [];
    }

    const around = neighbors(cell);
    const flagged = around.filter((neighbor) => flags.has(neighbor));

    if (flagged.length !== count) {
        return [];
    }

    return around.filter(
        (neighbor) => cells[neighbor] === null && !flags.has(neighbor),
    );
}

/**
 * Arrow keys move through the board like through the commit activity graph, stopping at its edges.
 */
export function move(cell: number, key: string): number | null {
    const row = Math.floor(cell / columns);
    const column = cell % columns;

    switch (key) {
        case 'ArrowUp':
            return row > 0 ? cell - columns : cell;
        case 'ArrowDown':
            return row < rows - 1 ? cell + columns : cell;
        case 'ArrowLeft':
            return column > 0 ? cell - 1 : cell;
        case 'ArrowRight':
            return column < columns - 1 ? cell + 1 : cell;
        default:
            return null;
    }
}

export function formatDuration(
    milliseconds: number,
    isPrecise: boolean,
): string {
    const seconds = Math.max(0, milliseconds) / 1000;

    return isPrecise ? `${seconds.toFixed(1)}s` : `${Math.floor(seconds)}s`;
}
