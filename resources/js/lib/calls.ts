/**
 * A new meet.jit.si room, named so nobody can guess their way into it.
 */
export function callUrl(): string {
    return `https://meet.jit.si/devnull-${crypto.randomUUID().replaceAll('-', '')}`;
}
