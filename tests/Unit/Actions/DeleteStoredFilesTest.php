<?php

declare(strict_types=1);

use App\Actions\CreateCommitReport;
use App\Actions\DeleteStoredFiles;
use App\Actions\DeleteUser;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;

it('retains failed account cleanup and retries it after the report metadata is gone', function (bool $throws, bool $deleteReporter): void {
    Storage::fake();
    $filesystem = Storage::getFacadeRoot();
    $disk = Storage::disk();
    $reporter = User::factory()->create();
    $commit = Commit::factory()->create();
    resolve(CreateCommitReport::class)->handle($reporter, $commit, 'other', null, [UploadedFile::fake()->image('evidence.png')]);
    $report = CommitReport::query()->sole();
    $path = $report->images[0]['path'];

    Storage::shouldReceive('disk')->andReturnSelf();
    if ($throws) {
        Storage::shouldReceive('delete')->once()->andThrow(new RuntimeException('Storage unavailable'));
    } else {
        Storage::shouldReceive('delete')->once()->andReturn(false);
    }

    $deletedUser = $deleteReporter ? $reporter : $commit->user;
    resolve(DeleteUser::class)->handle($deletedUser);

    $this->assertModelMissing($deletedUser);
    $this->assertModelMissing($report);
    $pending = DB::table('pending_file_deletions')->sole();
    expect(json_decode($pending->paths, true))->toContain($path);
    $disk->assertExists($path);

    Storage::swap($filesystem);
    resolve(DeleteStoredFiles::class)->handle($pending->id);

    $disk->assertMissing($path);
    expect(DB::table('pending_file_deletions')->count())->toBe(0);
})->with([false, true])->with([false, true]);

it('rolls back retained cleanup without deleting files', function (): void {
    Storage::fake();
    Storage::disk()->put('report-images/keep', 'encrypted image');

    expect(function (): void {
        DB::transaction(function (): void {
            resolve(DeleteStoredFiles::class)->retain(['report-images/keep']);
            throw new RuntimeException('Abort deletion');
        });
    })->toThrow(RuntimeException::class, 'Abort deletion');

    expect(DB::table('pending_file_deletions')->count())->toBe(0);
    Storage::disk()->assertExists('report-images/keep');
});

it('ignores a cleanup record that has already been removed', function (): void {
    resolve(DeleteStoredFiles::class)->handle(1);

    expect(DB::table('pending_file_deletions')->count())->toBe(0);
});

it('reports malformed cleanup records without deleting them', function (object $pending): void {
    Exceptions::fake();

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('find')->once()->with(1)->andReturn($pending);
    DB::shouldReceive('table')->once()->with('pending_file_deletions')->andReturn($query);

    resolve(DeleteStoredFiles::class)->handle(1);

    Exceptions::assertReported(UnexpectedValueException::class);
})->with([
    'invalid record' => [(object) ['paths' => [], 'disk' => 'local']],
    'invalid paths' => [(object) ['paths' => '{"path":"file"}', 'disk' => 'local']],
    'non-string path' => [(object) ['paths' => '["file",1]', 'disk' => 'local']],
]);
