<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Actions\DeleteUser;
use App\Actions\RestoreCommit;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('rejects a second restore even with a stale deleted model', function (): void {
    $commit = Commit::factory()->create();
    $deleted = resolve(DeleteCommit::class)->handle($commit);
    $action = resolve(RestoreCommit::class);
    $action->handle($commit->user, $deleted);

    expect(fn () => $action->handle($commit->user, $deleted))->toThrow(ValidationException::class);
    $this->assertNotSoftDeleted($commit);
});

it('checks the current deadline instead of a stale model timestamp', function (): void {
    $commit = Commit::factory()->create();
    $deleted = resolve(DeleteCommit::class)->handle($commit);
    $deleted->forceFill(['deleted_at' => now()->addHour()]);
    $this->travel(30)->seconds();

    expect(fn () => resolve(RestoreCommit::class)->handle($commit->user, $deleted))->toThrow(ValidationException::class);
    $this->assertSoftDeleted($commit);
});

it('does not extend the recovery window for a repeated stale delete', function (): void {
    $commit = Commit::factory()->create();
    $action = resolve(DeleteCommit::class);
    $deleted = $action->handle($commit);
    $deadline = $deleted->deleted_at;
    $this->travel(20)->seconds();

    expect(fn () => $action->handle($commit))->toThrow(ModelNotFoundException::class);
    expect($deleted->refresh()->deleted_at)->toEqual($deadline);
});

it('cannot restore with stale models after deleting the account', function (): void {
    $commit = Commit::factory()->create();
    $owner = $commit->user;
    $deleted = resolve(DeleteCommit::class)->handle($commit);
    resolve(DeleteUser::class)->handle($owner);

    expect(fn () => resolve(RestoreCommit::class)->handle($owner, $deleted))->toThrow(ModelNotFoundException::class);
    expect(Commit::withTrashed()->count())->toBe(0);
});

it('checks ownership inside the action', function (): void {
    $commit = Commit::factory()->create();
    $deleted = resolve(DeleteCommit::class)->handle($commit);
    $intruder = User::factory()->create();

    expect(fn () => resolve(RestoreCommit::class)->handle($intruder, $deleted))->toThrow(HttpException::class);
    $this->assertSoftDeleted($commit);
});
