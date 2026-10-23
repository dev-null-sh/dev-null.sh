import AppWordmark from '@/components/app-wordmark';

export function WordmarkText({ children }: { children: string }) {
    return children
        .split(
            /(?<![\p{L}\p{M}\p{N}_./-])(\/dev\/null)(?![\p{L}\p{M}\p{N}_/-])/u,
        )
        .map((part, index) =>
            index % 2 === 0 ? (
                part
            ) : (
                <span
                    key={index}
                    className="font-sans font-semibold tracking-tight whitespace-nowrap"
                >
                    <AppWordmark />
                </span>
            ),
        );
}
