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

final class UpdateSponsorsOrgRequest extends FormRequest
{
    public function authorize(): Response
    {
        return $this->org() instanceof Conversation ? Response::allow() : Response::denyAsNotFound();
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

    public function org(): ?Conversation
    {
        $user = $this->user();
        assert($user instanceof User);

        return Conversation::sponsorsOrgOf($user);
    }

    public function from(): SponsorsFrom
    {
        $from = $this->enum('from', SponsorsFrom::class);
        assert($from instanceof SponsorsFrom);

        return $from;
    }
}
