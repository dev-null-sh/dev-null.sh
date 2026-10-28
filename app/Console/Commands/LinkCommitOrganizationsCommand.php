<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\UpdateCommitTopics;
use App\Models\Commit;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

#[Signature('commits:link-organizations')]
#[Description('Link every approved commit to the GitHub organizations it mentions, asking GitHub about the handles no user has')]
final class LinkCommitOrganizationsCommand extends Command
{
    /**
     * Safe to run again: handles already known, or already found not to be an organization, cost no request.
     */
    public function handle(UpdateCommitTopics $updateCommitTopics): int
    {
        $linked = 0;
        $failed = 0;

        foreach (Commit::query()->approved()->where('body', 'like', '%@%')->lazyById() as $commit) {
            if ($commit->mentionedNonUsers() === []) {
                continue;
            }

            try {
                $retryAt = $updateCommitTopics->handle($commit);
            } catch (RequestException|ConnectionException $exception) {
                if ($exception instanceof RequestException && in_array($exception->response->status(), [403, 429], true)) {
                    $this->components->error("GitHub rate limit reached after {$linked} commits. Run the command again later.");

                    return self::FAILURE;
                }

                report($exception);

                $this->components->warn("Could not look up the organizations of commit {$commit->id}.");

                $failed++;

                continue;
            }

            if ($retryAt instanceof CarbonImmutable) {
                $this->components->error("GitHub rate limit reached after {$linked} commits. Run the command again after {$retryAt->toDateTimeString()} UTC.");

                return self::FAILURE;
            }

            $linked++;
        }

        $this->components->info("Looked up the organizations of {$linked} commits.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
