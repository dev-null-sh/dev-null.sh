import type { Commit } from '@/types';

type StarState = Pick<Commit, 'id' | 'starred' | 'stars_count'>;

export function createCommitStarMutations() {
    const states = new Map<string, { version: number; pending: boolean }>();
    const stateFor = (id: string) => {
        let state = states.get(id);

        if (!state) {
            state = { version: 0, pending: false };
            states.set(id, state);
        }

        return state;
    };

    return {
        begin(id: string): (() => void) | null {
            const state = stateFor(id);

            if (state.pending) {
                return null;
            }

            state.version++;
            state.pending = true;

            return () => {
                state.pending = false;
            };
        },
        prepare(id: string, update: (stars: StarState) => void) {
            const state = stateFor(id);

            if (state.pending) {
                return null;
            }

            const version = state.version;

            return (stars: StarState) => {
                // Duplicate controls share a version so no older poll can undo a click.
                if (state.version === version) {
                    update(stars);
                }
            };
        },
    };
}
