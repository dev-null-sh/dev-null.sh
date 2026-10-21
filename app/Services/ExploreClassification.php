<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Commit;
use App\Models\ExploreTopic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @phpstan-type Definition array{ref: string, kind: string, key: string, title: string, categories: list<string>, subjects: list<string>, aliases: list<string>}
 * @phpstan-type Assignment array{ref: string, topics: list<string>}
 * @phpstan-type Output array{topics: list<Definition>, assignments: list<Assignment>}
 */
final readonly class ExploreClassification
{
    /**
     * @param  array<array-key, mixed>  $output
     *
     * @phpstan-assert Output $output
     */
    public function validate(array $output): void
    {
        Validator::make($output, $this->rules())->validate();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'topics' => ['present', 'array', 'list', 'max:150'],
            'topics.*' => ['array:ref,kind,key,title,categories,subjects,aliases'],
            'topics.*.ref' => ['required', 'string', 'regex:/^n[a-zA-Z0-9_-]{1,30}$/', 'distinct'],
            'topics.*.kind' => ['required', Rule::in(['subject', 'story'])],
            'topics.*.key' => ['required', 'string', 'max:160', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/'],
            'topics.*.title' => ['required', 'string', 'max:240'],
            'topics.*.categories' => ['present', 'array', 'list', 'max:4'],
            'topics.*.categories.*' => [Rule::in(array_keys(ExploreTopic::CATEGORIES)), 'distinct'],
            'topics.*.subjects' => ['present', 'array', 'list', 'max:10'],
            'topics.*.subjects.*' => ['string'],
            'topics.*.aliases' => ['present', 'array', 'list', 'max:10'],
            'topics.*.aliases.*' => ['string', 'max:160'],
            'assignments' => ['present', 'array', 'list', 'max:50'],
            'assignments.*' => ['array:ref,topics'],
            'assignments.*.ref' => ['required', 'string', 'distinct'],
            'assignments.*.topics' => ['present', 'array', 'list', 'max:10'],
            'assignments.*.topics.*' => ['string', 'distinct'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $output
     * @param  Collection<int, Commit>  $commits
     * @param  array<string, ExploreTopic>  $catalog
     * @return array{topics: list<array{ref: string, kind: string, key: string, title: string, categories: list<string>, subjects: list<string>, aliases: list<string>}>, assignments: list<array{ref: string, topics: list<string>}>}
     */
    public function decode(array $output, Collection $commits, array $catalog): array
    {
        $this->validate($output);
        $data = $output;
        $definitions = collect($data['topics'])->keyBy('ref');
        $known = [...array_keys($catalog), ...$definitions->keys()->all()];
        $assignments = collect($data['assignments'])->filter(function (array $row) use ($commits, $known, $definitions, $catalog): bool {
            if (! preg_match('/^c(0|[1-9][0-9]*)$/', $row['ref']) || (int) mb_substr($row['ref'], 1) >= $commits->count()
                || array_diff($row['topics'], $known) !== []) {
                return false;
            }

            foreach ($row['topics'] as $ref) {
                $definition = $definitions->get($ref);
                if ($definition === null) {
                    continue;
                }

                foreach ($definition['subjects'] as $parent) {
                    if (($catalog[$parent]->kind ?? $definitions->get($parent)['kind'] ?? null) !== 'subject' || $definition['kind'] !== 'story') {
                        return false;
                    }
                }
            }

            return true;
        })->values()->all();

        return ['topics' => array_values($definitions->all()), 'assignments' => array_values($assignments)];
    }
}
