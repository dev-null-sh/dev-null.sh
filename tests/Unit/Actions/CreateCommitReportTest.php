<?php

declare(strict_types=1);

use App\Actions\CreateCommitReport;
use App\Actions\DeleteUser;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('rejects stale report requests after account deletion without writing attachments', function (bool $deleteReporter): void {
    Storage::fake();
    $reporter = User::factory()->create();
    $commit = Commit::factory()->create();

    resolve(DeleteUser::class)->handle($deleteReporter ? $reporter : $commit->user);

    expect(fn () => resolve(CreateCommitReport::class)->handle($reporter, $commit, 'other', null, [UploadedFile::fake()->image('evidence.png')]))
        ->toThrow(ModelNotFoundException::class);

    expect(CommitReport::query()->count())->toBe(0)
        ->and(Storage::disk()->allFiles('report-images'))->toBe([]);
})->with([true, false]);
