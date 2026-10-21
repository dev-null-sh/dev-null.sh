<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SponsorsFrom;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class CreateSponsorsOrgRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return match (true) {
            ! $user->remotes_enabled || ! $user->has_sponsors_listing => Response::deny('A sponsors org needs remotes on and a GitHub Sponsors listing.'),
            Conversation::sponsorsOrgOf($user) instanceof Conversation => Response::deny('You already have a sponsors org.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', Rule::enum(SponsorsFrom::class)],
        ];
    }

    public function from(): SponsorsFrom
    {
        $from = $this->enum('from', SponsorsFrom::class);
        assert($from instanceof SponsorsFrom);

        return $from;
    }
}
