<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Commit;
use App\Models\RepositoryMirror;
use App\Models\RepositoryMirrorEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** Opt-in loopback fixture, never registered outside APP_ENV=local. */
final readonly class ReactionDemoController
{
    public function __invoke(Request $request): Response
    {
        abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 404);
        $user = User::query()->where('username', 'reacji-demo')->firstOrFail();
        if (Auth::id() !== $user->id) {
            Auth::login($user);
            $request->session()->regenerate();
        }
        $mirrors = RepositoryMirror::query()->where('user_id', $user->id)->orderBy('repository')->get();

        return Inertia::render('reaction-demo', [
            'commit' => Commit::query()->where('body', 'Shipped a smaller build and a faster test suite. What should we automate next?')->withCardDataFor($user)->firstOrFail(),
            'mirrors' => $mirrors->map(fn (RepositoryMirror $mirror): array => $mirror->summary()),
            'events' => RepositoryMirrorEvent::query()->whereIn('repository_mirror_id', $mirrors->modelKeys())->latest('id')->limit(20)->get()->map(fn (RepositoryMirrorEvent $event): array => ['id' => $event->id, 'repository' => $mirrors->find($event->repository_mirror_id)?->repository, 'payload' => $event->payload]),
            'workflow' => file_get_contents(base_path('docs/examples/emoji-actions.yml')),
        ]);
    }
}
