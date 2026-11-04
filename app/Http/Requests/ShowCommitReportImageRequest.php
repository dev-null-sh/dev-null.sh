<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowCommitReportImageRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $report = $this->route('report');
        assert($report instanceof CommitReport);
        $image = $report->images[(int) $this->route('image')] ?? null;

        return ($user->isAdmin() || $report->reporter_id === $user->id) && $image !== null && Storage::disk()->exists($image['path'])
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** @return array{} */
    public function rules(): array
    {
        return [];
    }
}
