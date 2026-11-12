<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Log;
use App\Models\Commit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Arr;

final class ShowLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'since' => ['nullable', 'uuid'],
            'since_at' => ['nullable', 'date'],
            // The oldest loaded commits, oldest first, so the range holds even when the oldest is hidden or gone.
            'through' => ['nullable', 'string', 'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(,[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}){0,19}$/i'],
            'pending' => ['sometimes', 'array', 'list', 'max:100'],
            'pending.*' => ['required', 'uuid', 'distinct'],
            'arrived' => ['sometimes', 'array', 'list', 'max:100'],
            'arrived.*' => ['required', 'uuid', 'distinct'],
            'everything' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Keep the loaded range when a follow changes who belongs in the log, or new commits arrive on top of it: the
     * oldest of the loaded commits named that the user can still see, as older ones may have been hidden or erased.
     */
    public function through(): ?Commit
    {
        if (! $this->filled('through')) {
            return null;
        }

        $ids = explode(',', mb_strtolower($this->string('through')->value()));
        $visible = Commit::query()->withTrashed()->visibleTo($this->user())->whereKey($ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            if ($visible->has($id)) {
                return $visible->get($id);
            }
        }

        return null;
    }

    /**
     * The timestamp originally observed for whichever loaded boundary survived.
     * Its approval time may have changed since the page was rendered.
     */
    public function throughAt(?Commit $through): ?CarbonInterface
    {
        if (! $through instanceof Commit) {
            return null;
        }

        $ids = explode(',', mb_strtolower((string) $this->header('X-Log-Through', '')));
        $times = explode(',', (string) $this->header('X-Log-Through-At', ''));
        $index = array_search($through->id, $ids, true);

        if ($index === false || count($ids) !== count($times)) {
            return null;
        }

        $time = $times[$index];

        if (strlen($time) > 40 || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $time) !== 1) {
            return null;
        }

        try {
            $observed = CarbonImmutable::parse($time);
            $current = $through->approved_at ?? $through->created_at;

            // A commit cannot have appeared before creation or after its current approval.
            return $observed->betweenIncluded($through->created_at, $current) ? $observed : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * The newest commit the user has already seen, even if it was deleted since.
     */
    public function since(): ?Commit
    {
        return $this->filled('since')
            ? Commit::query()->withTrashed()->find($this->string('since')->value())
            : null;
    }

    /**
     * The cursor of the page to load, or none when it was taken from a log that pages on different columns.
     */
    public function cursor(Log $log): string
    {
        $cursor = Cursor::fromEncoded($this->string('cursor')->value());
        $columns = $log->isRanked() ? ['trending_seed', 'trending_score', 'id'] : ['feed_at', 'id'];

        return $cursor instanceof Cursor && Arr::has($cursor->toArray(), $columns) ? $cursor->encode() : '';
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        /** @var list<string> $pending */
        $pending = $this->validated('pending', []);

        return $pending;
    }

    /**
     * Commits the log heard about that it doesn't show, which may belong among the ones it does, as when an older one
     * was approved.
     *
     * @return list<string>
     */
    public function arrived(): array
    {
        /** @var list<string> $arrived */
        $arrived = $this->validated('arrived', []);

        return $arrived;
    }

    public function prepareForValidation(): void
    {
        $this->merge(['through' => $this->header('X-Log-Through')]);
    }
}
