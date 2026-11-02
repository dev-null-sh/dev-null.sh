<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ScheduledCommit;
use Illuminate\Foundation\Http\FormRequest;

final class CreateScheduledCommitSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scheduled = $this->route('scheduledCommit');
        assert($scheduled instanceof ScheduledCommit);

        return $this->user()?->id === $scheduled->user_id;
    }

    /** @return array{} */
    public function rules(): array
    {
        return [];
    }
}
