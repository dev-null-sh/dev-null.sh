<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\Services\AnalyticsProvider;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @phpstan-import-type TrafficFilter from AnalyticsProvider
 */
final class ShowTrafficRequest extends FormRequest
{
    /**
     * @var list<TrafficFilter>
     */
    private const array FILTERS = ['page', 'referrer', 'country', 'device', 'browser'];

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
            ...collect(self::FILTERS)->mapWithKeys(fn (string $filter): array => [$filter => ['nullable', 'string', 'max:2000']]),
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

    /**
     * The traffic filters present in the query. An empty one, `?referrer=`, matches direct visits or unknown values.
     *
     * @return array<TrafficFilter, string>
     */
    public function filters(): array
    {
        return collect(self::FILTERS)
            ->filter(fn (string $filter): bool => $this->has($filter))
            ->mapWithKeys(fn (string $filter): array => [$filter => $this->string($filter)->value()])
            ->all();
    }
}
