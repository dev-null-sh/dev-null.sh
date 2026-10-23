import AppWordmark from '@/components/app-wordmark';
import { codePattern } from '@/lib/code';

export function WordmarkText({ children }: { children: string }) {
    return children.split(codePattern).flatMap((segment, segmentIndex) =>
        segmentIndex % 2 === 1
            ? segment
            : segment
                  .split(
                      /(?<![\p{L}\p{M}\p{N}_./-])(\/dev\/null)(?![\p{L}\p{M}\p{N}_/-])/u,
                  )
                  .map((part, index) =>
                      index % 2 === 0 ? (
                          part
                      ) : (
                          <span
                              key={`${segmentIndex}-${index}`}
                              className="font-sans font-semibold tracking-tight whitespace-nowrap"
                          >
                              <AppWordmark />
                          </span>
                      ),
                  ),
    );
}
