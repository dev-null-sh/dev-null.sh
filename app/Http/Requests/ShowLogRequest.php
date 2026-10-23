<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;

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
        ];
    }

    /**
     * Keep the loaded range when a follow changes who belongs in the log.
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
     * When the newest commit the user saw in their Following log was committed, as their browser remembers it.
     */
    public function followingSeenAt(): ?CarbonInterface
    {
        $seenAt = $this->cookie('following_seen_at');

        return is_string($seenAt) ? rescue(fn (): CarbonInterface => Date::createFromFormat('Y-m-d\TH:i:s.uP', $seenAt), report: false) : null;
    }

    public function prepareForValidation(): void
    {
        $this->merge(['through' => $this->header('X-Log-Through')]);
    }
}
