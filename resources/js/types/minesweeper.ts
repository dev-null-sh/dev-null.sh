export type MinesweeperGameStatus = 'playing' | 'won' | 'lost';

export type MinesweeperGame = {
    id: string;
    status: MinesweeperGameStatus;
    cells: (number | null)[];
    mines: number[] | null;
    exploded_cell: number | null;
    duration_ms: number | null;
    created_at: string;
    finished_at: string | null;
};

export type MinesweeperScores = {
    game: MinesweeperGame | null;
    best: {
        duration_ms: number;
        user: { username: string; name: string };
    } | null;
    personal_best: number | null;
    played: number;
};
