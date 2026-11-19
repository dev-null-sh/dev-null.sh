<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContributorHelp;
use App\Models\ContributorRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateContributorRequest
{
    public function __construct(private CreateCommit $commits) {}

    public function handle(User $user, string $body, string $projectUrl, ContributorHelp $kind): ContributorRequest
    {
        return DB::transaction(function () use ($user, $body, $projectUrl, $kind): ContributorRequest {
            $commit = $this->commits->handle($user, $body, linkPreview: false);

            return ContributorRequest::query()->create([
                'user_id' => $user->id, 'commit_id' => $commit->id, 'project_url' => $projectUrl, 'kind' => $kind,
            ]);
        });
    }
}
