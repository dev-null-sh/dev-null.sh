import { typedProse } from '@/lib/code';

/** A command on its own final line, outside code. The rest of the draft stays byte-for-byte intact. */
export function shipitDraft(body: string): string | null {
    const command = /(?:^|\r?\n)\/shipit[\t ]*\n?$/.exec(body);

    if (!command || !typedProse(body).at(-1)?.endsWith(command[0])) {
        return null;
    }

    return body.slice(0, command.index);
}
