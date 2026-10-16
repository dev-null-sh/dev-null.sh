<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteCommitReportRequest extends FormRequest
{
    /**
     * Reports on deleted or no longer published commits have left the queue, so they look like they do not exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        if (! $user->isAdmin()) {
            return Response::deny();
        }

        $report = $this->route('report');
        assert($report instanceof CommitReport);

        return $report->commit?->status === CommitStatus::Approved
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
