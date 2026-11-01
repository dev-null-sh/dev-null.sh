<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Models\UserReport;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteUserReportRequest extends FormRequest
{
    /**
     * Reports on banned people have left the queue, so they look like they do not exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        if (! $user->isAdmin()) {
            return Response::deny();
        }

        $report = $this->route('report');
        assert($report instanceof UserReport);

        return $report->user->isBanned()
            ? Response::denyAsNotFound()
            : Response::allow();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
