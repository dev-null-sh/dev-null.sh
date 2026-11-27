<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateOrgCall;
use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\OrgCallQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-import-type Call from OrgCallQuery
 * @phpstan-import-type Signal from OrgCallQuery
 */
final class UpdateOrgCallRequest extends FormRequest
{
    /**
     * The call as authorizing the check-in found it, so it is only read once.
     *
     * @var Call|null
     */
    private ?array $call = null;

    /**
     * Only someone still in the org's call keeps checking in to it.
     */
    public function authorize(OrgCallQuery $calls): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $org = $this->route('org');
        assert($org instanceof Conversation);

        if (! $user->remotes_enabled || ! $org->roleOf($user) instanceof OrgRole) {
            return Response::denyAsNotFound();
        }

        $this->call = $calls->get($org);

        return isset($this->call['members'][$user->id]) ? Response::allow() : Response::deny("You're no longer in this call.");
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'signals' => ['present', 'array', 'max:'.CreateOrgCall::MAX_MEMBERS],
            'signals.*' => ['array:id,type,sdp'],
            'signals.*.id' => ['required', 'string', 'max:64'],
            'signals.*.type' => ['required', 'string', 'in:offer,answer'],
            'signals.*.sdp' => ['required', 'string', 'starts_with:v=0', 'max:10000'],
        ];
    }

    /**
     * The call the user checks in to.
     *
     * @return Call|null
     */
    public function call(): ?array
    {
        return $this->call;
    }

    /**
     * The signals for the others in the call, keyed by who each is for.
     *
     * @return array<string, Signal>
     */
    public function signals(): array
    {
        /** @var array<string, Signal> $signals */
        $signals = $this->validated('signals');

        return array_intersect_key($signals, $this->call['members'] ?? []);
    }
}
