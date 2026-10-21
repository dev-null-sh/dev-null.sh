<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GitHub;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;
use stdClass;

#[Signature('contributors:backfill-reporters {path : Local JSON file containing reviewed issue numbers and immutable reporter GitHub IDs}')]
#[Description('Award historical in-app reporters from a reviewed identity mapping')]
final class BackfillIssueReportersCommand extends Command
{
    public function handle(GitHub $github): int
    {
        $path = $this->argument('path');
        assert(is_string($path));
        $repository = config()->string('services.github.repository');

        try {
            $reporters = $this->reporters($path, $repository);

            // Verify the whole reviewed mapping before awarding anyone, so a bad entry or outage changes nothing.
            foreach (array_keys($reporters) as $number) {
                $issue = $github->request()->get("repos/{$repository}/issues/{$number}")->throw()->json();
                $this->verifyIssue($issue, (string) $number);
            }
        } catch (InvalidArgumentException|JsonException|FileNotFoundException $exception) {
            $this->components->error('Invalid reporter mapping: '.$exception->getMessage());

            return self::FAILURE;
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            $this->components->error('Could not verify historical reports with GitHub. Try again later.');

            return self::FAILURE;
        }

        $awarded = User::query()->where('is_contributor', false)
            ->whereIn('github_id', array_values($reporters))
            ->update(['is_contributor' => true]);

        $this->components->info("Awarded the contributor badge to {$awarded} users.");

        return self::SUCCESS;
    }

    /**
     * The mapping is reviewed evidence, not usernames parsed from untrusted issue text. Legacy reports never stored
     * their authenticated reporter's immutable ID, and a renamed GitHub username can be reclaimed by someone else.
     *
     * @return array<int|string, string>
     */
    private function reporters(string $path, string $repository): array
    {
        throw_if(str_contains($path, '://') || ! File::isFile($path), InvalidArgumentException::class, 'Provide an existing local JSON file.');

        $mapping = json_decode(File::get($path), flags: JSON_THROW_ON_ERROR);

        throw_unless($mapping instanceof stdClass
            && isset($mapping->repository, $mapping->reporters)
            && $mapping->repository === $repository
            && $mapping->reporters instanceof stdClass,
            InvalidArgumentException::class, 'Expected the configured repository and a reporters object.');

        $reporters = [];

        foreach (get_object_vars($mapping->reporters) as $number => $githubId) {
            throw_unless(preg_match('/\A[1-9][0-9]*\z/', (string) $number) === 1
                && is_string($githubId)
                && preg_match('/\A[1-9][0-9]*\z/', $githubId) === 1,
                InvalidArgumentException::class, 'Issue numbers and GitHub IDs must be canonical positive integer strings.');

            $reporters[$number] = $githubId;
        }

        return $reporters;
    }

    private function verifyIssue(mixed $issue, string $number): void
    {
        $author = data_get($issue, 'user.id');
        $issueNumber = data_get($issue, 'number');
        $body = data_get($issue, 'body');
        $profile = str_replace('REPORTER', '\\1', preg_quote(route('user.show', ['user' => 'REPORTER']), '~'));
        $footer = '~\n\n---\n\nReported by @([a-zA-Z0-9](?:[a-zA-Z0-9-]{0,37}[a-zA-Z0-9])?) via \[/dev/null\]\('.$profile.'\)\.\z~';

        throw_unless(is_array($issue)
            && is_int($issueNumber)
            && (string) $issueNumber === $number
            && ! array_key_exists('pull_request', $issue)
            && is_int($author)
            && (string) $author === config()->string('services.github_app.bot_id')
            && is_string($body)
            && preg_match($footer, $body) === 1,
            InvalidArgumentException::class, "Issue #{$number} is not a verified legacy in-app report.");
    }
}
