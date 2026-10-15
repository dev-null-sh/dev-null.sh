<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\GitHubRepoPet;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateRepoPetRequest extends FormRequest
{
    /** @var array{repository: string, repository_id: int, workflow_id: int, workflow: string}|null */
    private ?array $petConfiguration = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'repository' => ['required', 'string', 'max:140', 'regex:~\A[A-Za-z0-9][A-Za-z0-9-]{0,38}/[A-Za-z0-9_.-]{1,100}\z~'],
            'workflow_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $options = resolve(GitHubRepoPet::class)->options($this->string('repository')->toString());

            if ($options === null) {
                $validator->errors()->add('repository', __('Could not load that public repository. Check the name or try again shortly.'));

                return;
            }

            foreach ($options['workflows'] as $workflow) {
                if ($workflow['id'] === $this->integer('workflow_id')) {
                    $this->petConfiguration = [
                        'repository' => $options['repository'],
                        'repository_id' => $options['repository_id'],
                        'workflow_id' => $workflow['id'],
                        'workflow' => $workflow['name'],
                    ];

                    return;
                }
            }

            $validator->errors()->add('workflow_id', __('Choose an active workflow from this repository.'));
        }];
    }

    /** @return array{repository: string, repository_id: int, workflow_id: int, workflow: string} */
    public function configuration(): array
    {
        assert($this->petConfiguration !== null);

        return $this->petConfiguration;
    }
}
