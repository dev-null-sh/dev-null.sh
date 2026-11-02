<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Log;
use App\Models\Commit;
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
            'through' => ['nullable', 'uuid'],
            'pending' => ['sometimes', 'array', 'list'],
            'pending.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * Keep the loaded range when a follow changes who belongs in the log, or new commits arrive on top of it.
     */
    public function through(): ?Commit
    {
        return $this->filled('through')
            ? Commit::query()->withTrashed()->visibleTo($this->user())->find($this->string('through')->value())
            : null;
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
        $columns = $log->isRanked() ? ['trending_seed', 'trending_score', 'id'] : ['created_at', 'id'];

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

    public function prepareForValidation(): void
    {
        $this->merge(['through' => $this->header('X-Log-Through')]);
    }
}
