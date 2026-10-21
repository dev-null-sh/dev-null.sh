<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExploreTopic;
use App\Models\User;
use App\Queries\ExploreContentQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateExploreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $topic = $this->route('topic');

        return $this->user() instanceof User && $topic instanceof ExploreTopic && $topic->suppressed_at === null
            && resolve(ExploreContentQuery::class)->members($topic, $this->user())->exists();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', Rule::in(array_keys(ExploreTopic::REASONS))]];
    }
}
