<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowProductMetricsRequest extends FormRequest
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
            'all_time' => ['nullable', 'boolean', 'prohibits:from,to'],
            'from' => ['nullable', 'required_with:to', Rule::date()->format('Y-m-d')->todayOrBefore()],
            'to' => ['nullable', 'required_with:from', Rule::date()->format('Y-m-d')->todayOrBefore(), 'after_or_equal:from'],
        ];
    }

    /**
     * Without dates, the page covers the last 30 days. All time starts at the first signup. Dates come in pairs.
     */
    public function isAllTime(): bool
    {
        return $this->boolean('all_time');
    }

    public function from(): CarbonImmutable
    {
        if ($this->filled('from')) {
            return CarbonImmutable::parse($this->string('from')->value());
        }

        if (! $this->isAllTime()) {
            return today()->subDays(29)->toImmutable();
        }

        $firstSignup = User::query()->whereNotIn('github_id', User::BOT_GITHUB_IDS)->min('created_at');

        return is_string($firstSignup) ? CarbonImmutable::parse($firstSignup)->startOfDay() : today()->toImmutable();
    }

    public function to(): CarbonImmutable
    {
        return $this->date('to', 'Y-m-d')?->toImmutable() ?? today()->toImmutable();
    }
}
