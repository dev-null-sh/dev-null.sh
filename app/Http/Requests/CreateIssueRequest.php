<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateIssueRequest extends FormRequest
{
    public const array ENVIRONMENT_FIELDS = ['browser', 'os', 'viewport', 'pixel_ratio', 'language', 'color_scheme', 'page'];

    /**
     * A commit may be a single character, but an issue needs enough to act on.
     */
    public const int MIN_BODY_LENGTH = 3;

    /**
     * The rules of every request that opens an issue, so the apps validate drafts as the web does.
     *
     * @return array<string, array<int, string|In>>
     */
    public static function issueRules(): array
    {
        return [
            'command' => ['required', Rule::in(['/feature', '/bug'])],
            'body' => ['required', 'string', 'min:'.self::MIN_BODY_LENGTH, 'max:'.Commit::MAX_BODY_LENGTH],
            'environment' => ['nullable', 'array:'.implode(',', self::ENVIRONMENT_FIELDS)],
            'environment.*' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * The environment fields the reporter filled, leaving out empty ones.
     *
     * @return array<string, string>
     */
    public static function filledEnvironment(FormRequest $request): array
    {
        /** @var array<string, string|null> $environment */
        $environment = $request->array('environment');

        return array_filter($environment, fn (?string $value): bool => $value !== null && $value !== '');
    }

    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * Issues carry text only: images are not accepted.
     *
     * @return array<string, array<int, string|In>>
     */
    public function rules(): array
    {
        return self::issueRules();
    }

    /**
     * @return array<string, string>
     */
    public function environment(): array
    {
        return self::filledEnvironment($this);
    }
}
