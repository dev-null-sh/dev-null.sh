<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RepositoryMirror;
use App\Models\User;
use App\Services\GitHubRepositoryMirror;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

final readonly class RepositoryMirrorController
{
    public function store(Request $request, #[CurrentUser] User $user, GitHubRepositoryMirror $github): RedirectResponse
    {
        /** @var array{repository: string, token: string, mirror_commits: bool} $data */
        $data = $request->validate([
            'repository' => ['required', 'string', 'max:200', 'regex:/\A[A-Za-z0-9][A-Za-z0-9-]*\/[A-Za-z0-9_.-]+\z/'],
            'token' => ['required', 'string', 'max:255'],
            'mirror_commits' => ['required', 'boolean'],
        ]);
        $verified = $github->verify($user, $data['repository'], $data['token']);
        DB::transaction(function () use ($user, $data, $verified): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $previous = RepositoryMirror::query()->where('user_id', $user->id)->where('repository_id', $verified['repository_id'])->lockForUpdate()->first();
            // Token renewal for the same destination preserves pending deliveries.
            if ($previous instanceof RepositoryMirror && $previous->repository_id === $verified['repository_id'] && $previous->private === $verified['private'] && $previous->branch === $verified['branch']) {
                $previous->forceFill([...$verified, 'token' => $data['token'], 'mirror_commits' => $data['mirror_commits'], 'last_error' => null])->save();

                return;
            }
            $previous?->delete();
            if (RepositoryMirror::query()->where('user_id', $user->id)->count() >= 10) {
                throw \Illuminate\Validation\ValidationException::withMessages(['repository' => 'You can connect up to 10 repositories.']);
            }
            (new RepositoryMirror)->forceFill([
                ...$verified, 'user_id' => $user->id, 'token' => $data['token'], 'mirror_commits' => $data['mirror_commits'],
            ])->save();
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Repository connected. New activity can now be mirrored.']);

        return back();
    }

    public function destroy(#[CurrentUser] User $user, RepositoryMirror $mirror): RedirectResponse
    {
        abort_unless($mirror->user_id === $user->id, 403);
        DB::transaction(function () use ($user, $mirror): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            RepositoryMirror::query()->whereKey($mirror->id)->lockForUpdate()->first()?->delete();
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Disconnected. Existing files and Git history remain in your repository.']);

        return back();
    }
}
