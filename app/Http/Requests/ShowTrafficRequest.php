<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowTrafficRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->isAdmin() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', Rule::date()->format('Y-m-d')->todayOrBefore()],
            'to' => ['nullable', Rule::date()->format('Y-m-d')->todayOrBefore(), Rule::when($this->filled('from'), 'after_or_equal:from')],
        ];
    }

    /**
     * The traffic range, the last seven days by default.
     */
    public function from(): CarbonImmutable
    {
        return $this->date('from', 'Y-m-d')?->toImmutable() ?? today()->subDays(6)->toImmutable();
    }

    public function to(): CarbonImmutable
    {
        return $this->date('to', 'Y-m-d')?->toImmutable() ?? today()->toImmutable();
    }
}
