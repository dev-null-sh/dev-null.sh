<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\ActivityStreams;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class ShowWebFingerRequest extends FormRequest
{
    public function authorize(ActivityStreams $streams): Response
    {
        $resource = (string) $this->query('resource');

        return str_starts_with($resource, 'acct:') && str_contains($resource, '@') && mb_strtolower(Str::afterLast($resource, '@')) === $streams->host()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'resource' => ['required', 'string', 'max:2048'],
        ];
    }

    public function username(): string
    {
        return Str::between($this->string('resource')->toString(), 'acct:', '@');
    }
}
