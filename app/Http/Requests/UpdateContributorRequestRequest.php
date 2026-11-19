<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContributorRequestStatus;
use App\Models\ContributorRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateContributorRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('contributorRequest');
        $user = $this->user();

        return $item instanceof ContributorRequest && $user instanceof User && $item->user_id === $user->id && $item->commit !== null;
    }

    /** @return array<string, list<string|Enum>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ContributorRequestStatus::class)]];
    }
}
