<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('local') || ! config('repository-mirrors.demo')) {
    throw new RuntimeException('Requires APP_ENV=local and DEVNULL_REACTION_DEMO=true.');
}
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Database\Eloquent\Model::unguarded(function (): void {
    $reader = App\Models\User::query()->firstOrCreate(['username' => 'reacji-demo'], [
        'github_id' => '900000001', 'name' => 'You (local demo)', 'onboarded_at' => now(),
    ]);
    $author = App\Models\User::query()->firstOrCreate(['username' => 'demo-author'], [
        'github_id' => '900000002', 'name' => 'Alex (demo)', 'onboarded_at' => now(),
    ]);
    App\Models\Commit::query()->firstOrCreate([
        'user_id' => $author->id, 'body' => 'Shipped a smaller build and a faster test suite. What should we automate next?',
    ], ['status' => App\Enums\CommitStatus::Approved]);
    App\Models\RepositoryMirror::query()->firstOrCreate(['user_id' => $reader->id, 'repository_id' => 'demo-only'], [
        'repository' => 'you/dev-null-archive', 'repository_id' => 'demo-only', 'branch' => 'main',
        'private' => true, 'token' => 'inert-local-demo-token', 'mirror_commits' => true,
    ]);
    App\Models\RepositoryMirror::query()->firstOrCreate(['user_id' => $reader->id, 'repository_id' => 'demo-public'], [
        'repository' => 'you/build-log', 'branch' => 'main',
        'private' => false, 'token' => 'inert-local-demo-token', 'mirror_commits' => true,
    ]);
});
echo "Synthetic accounts ready. Open /_demo/reactions on the loopback server. No GitHub requests were made.\n";
