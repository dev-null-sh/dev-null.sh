import { createChoice } from '@/components/api-docs/choice';

const example = createChoice('api-docs:example');

export const chooseExample = example.choose;

/**
 * The client the reader picked last, such as Laravel: someone who picks it uses it, so every endpoint shows it.
 */
export const useChosenExample = example.useChosen;

/**
 * The tab to show: the one picked last when this endpoint has it, else the first.
 */
export function activeExample(
    labels: string[],
    chosenLabel: string | null,
): number {
    return Math.max(0, chosenLabel === null ? 0 : labels.indexOf(chosenLabel));
}
