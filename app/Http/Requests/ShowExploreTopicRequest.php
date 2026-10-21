<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExploreTopic;
use App\Queries\ExploreTopicsQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowExploreTopicRequest extends FormRequest
{
    public function authorize(): Response
    {
        return resolve(ExploreTopicsQuery::class)->detail($this->topic(), $this->user()) !== null
            ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['order' => ['nullable', Rule::in($this->route('kind') === 'stories' ? ['top', 'latest', 'related'] : ['top', 'latest'])]];
    }

    public function topic(): ExploreTopic
    {
        return ExploreTopic::query()->where('kind', $this->route('kind') === 'subjects' ? 'subject' : 'story')
            ->where('key', $this->route('key'))->firstOrFail();
    }
}
