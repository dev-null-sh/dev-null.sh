/**
 * Keep in sync with app/Services/Branches.php (NAME_PATTERN and MAX_PER_COMMIT).
 */
export const maxBranchesPerCommit = 1;

const namePattern = /^(?=[a-z0-9][a-z0-9_-]{0,49}$)(?=.*[a-z])[a-z0-9_-]+$/;

/**
 * Trim, strip a leading "#", lowercase. Empty becomes null.
 */
export function normalizeBranchName(value: string): string | null {
    const normalized = value.trim().replace(/^#+/, "").toLowerCase();

    return normalized === "" ? null : normalized;
}

export function isValidBranchName(name: string): boolean {
    return namePattern.test(name);
}
