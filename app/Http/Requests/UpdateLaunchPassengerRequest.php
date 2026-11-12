<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Enums\CommitType;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateLaunchPassengerRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return match (true) {
            $commit->type !== CommitType::Launch, $commit->status !== CommitStatus::Approved, ! $commit->isVisibleTo($user) => Response::denyAsNotFound(),
            $this->isMethod('post') && $commit->isOwnedBy($user) => Response::deny(),
            default => Response::allow(),
        };
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
