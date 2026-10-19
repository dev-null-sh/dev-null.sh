<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ScheduledCommit;
use App\Models\User;
use App\Services\PasskeyChallenges;
use App\Services\SignedWebCommits;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * An unsigned scheduled commit that is still waiting would be posted unsigned after signing is turned on.
 */
final class UpdateSigningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            ...SignedWebCommits::rules(),
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        if ($this->boolean('enabled') && ! $user->passkeys()->exists()) {
            return [function (Validator $validator): void {
                $validator->errors()->add('enabled', 'Add a passkey first.');
            }];
        }

        if ($this->boolean('enabled') && ScheduledCommit::query()->whereBelongsTo($user)->whereNull('submitted_at')->whereNull('canceled_at')->whereNull('signature')->exists()) {
            return [function (Validator $validator): void {
                $validator->errors()->add('enabled', 'Cancel your unsigned scheduled commits first, or post them signed.');
            }];
        }

        return [resolve(PasskeyChallenges::class)->confirmation($this, $user, $this->boolean('enabled') ? 'enable' : 'disable')];
    }
}
