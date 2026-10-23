export interface FsNode {
    mode: number;
    usedBytes: number;
}

export interface FsStream {
    node: FsNode;
    flags: number;
    position: number;
}

export interface PhpModule {
    ccall(
        name: 'phpw',
        returnType: 'number',
        argTypes: ['string'],
        args: [string],
    ): number;
    FS: {
        mkdirTree(path: string): void;
        writeFile(path: string, data: string): void;
        write(
            stream: FsStream,
            buffer: Uint8Array,
            offset: number,
            length: number,
            position?: number,
            canOwn?: boolean,
        ): number;
        doTruncate(stream: FsStream | null, node: FsNode, length: number): void;
        mknod(path: string, mode: number, dev: number): FsNode;
        symlink(target: string, path: string): FsNode;
        rename(from: string, to: string): void;
        unlink(path: string): void;
        isFile(mode: number): boolean;
        MAX_OPEN_FDS: number;
        ErrnoError: new (errno: number) => Error;
    };
}

export interface PhpOptions {
    instantiateWasm(
        imports: WebAssembly.Imports,
        done: (
            instance: WebAssembly.Instance,
            module: WebAssembly.Module,
        ) => void,
    ): object;
    stdout(byte: number): void;
    stderr(byte: number): void;
    printErr(text: string): void;
}

export default function createPhp(options: PhpOptions): Promise<PhpModule>;
