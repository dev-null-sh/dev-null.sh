<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OperationsEventLevel;
use App\Enums\OperationsEventSource;
use App\Enums\OperationsEventWindow;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListOperationsEventsRequest extends FormRequest
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
            'window' => ['nullable', Rule::enum(OperationsEventWindow::class)],
            'source' => ['nullable', Rule::enum(OperationsEventSource::class)],
            'level' => ['nullable', Rule::enum(OperationsEventLevel::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * The window the log covers, the last 24 hours by default.
     */
    public function window(): OperationsEventWindow
    {
        return $this->enum('window', OperationsEventWindow::class) ?? OperationsEventWindow::Day;
    }

    public function source(): ?OperationsEventSource
    {
        return $this->enum('source', OperationsEventSource::class);
    }

    public function level(): ?OperationsEventLevel
    {
        return $this->enum('level', OperationsEventLevel::class);
    }

    public function search(): string
    {
        return $this->string('search')->trim()->value();
    }
}
