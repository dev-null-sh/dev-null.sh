/**
 * Marks a commit posted as an organization, right after its handle. People and
 * organizations otherwise share the same header, and the square avatar alone is
 * easy to miss in a busy log. It sits next to the author on purpose: what the
 * commit was posted from (web, CLI, apps) belongs next to the timestamp instead.
 */
export function OrganizationBadge({ handle }: { handle: string }) {
    return (
        <span
            className="shrink-0 self-center rounded-sm bg-muted px-2 py-0.5 font-mono text-xs text-muted-foreground"
            title={`Posted by the @${handle} organization`}
            data-test="organization-badge"
        >
            Organization
        </span>
    );
}
