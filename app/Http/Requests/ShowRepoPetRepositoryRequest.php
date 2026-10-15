<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\GitHubRepoPet;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ShowRepoPetRepositoryRequest extends FormRequest
{
    /** @var array{repository: string, repository_id: int, workflows: list<array{id: int, name: string}>}|null */
    private ?array $repositoryOptions = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'repository' => ['required', 'string', 'max:140', 'regex:~\A[A-Za-z0-9][A-Za-z0-9-]{0,38}/[A-Za-z0-9_.-]{1,100}\z~'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->repositoryOptions = resolve(GitHubRepoPet::class)->options($this->string('repository')->toString());

            if ($this->repositoryOptions === null) {
                $validator->errors()->add('repository', __('Could not load that public repository. Check the name or try again shortly.'));
            }
        }];
    }

    /** @return array{repository: string, repository_id: int, workflows: list<array{id: int, name: string}>} */
    public function options(): array
    {
        assert($this->repositoryOptions !== null);

        return $this->repositoryOptions;
    }
}
