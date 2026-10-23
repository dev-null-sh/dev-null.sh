<?php

declare(strict_types=1);

use App\Actions\UpdateCommitAiDecision;
use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Enums\CommitVideoStatus;
use App\Jobs\ModerateCommit;
use App\Jobs\ProcessCommitVideo;
use App\Models\Commit;
use App\Models\CommitVideo;
use App\Models\User;
use App\Services\CommitVideos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Storage::fake();
    Queue::fake();
});

it('stages a private upload and dispatches processing once only after every byte arrives', function (): void {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson(route('commit-video.store'), ['size' => 9])->assertCreated()
        ->assertJsonPath('video.status', 'uploading')->assertJsonPath('video.url', null)
        ->assertJsonMissingPath('video.user_id')->assertJsonMissingPath('video.size');
    $video = CommitVideo::query()->sole();

    $this->postJson(route('commit-video-processing.store', $video))->assertUnprocessable();
    $this->call('PUT', $response->json('upload.url'), server: ['CONTENT_LENGTH' => 9], content: 'testvideo')->assertNoContent();
    $this->postJson(route('commit-video-processing.store', $video))->assertOk()->assertJsonPath('video.status', 'processing');
    $this->postJson(route('commit-video-processing.store', $video))->assertOk();
    $this->getJson(route('commit-video.show', $video))->assertOk()->assertJsonPath('video.status', 'processing');
    Queue::assertPushed(ProcessCommitVideo::class, 1);
    Storage::assertExists($video->path('source'));

    $this->deleteJson(route('commit-video.destroy', $video))->assertNoContent();
    Storage::assertMissing($video->path('source'));
    expect(CommitVideo::query()->count())->toBe(0);
});

it('sends an upload with the wrong size to processing, which deletes it at once', function (): void {
    $user = User::factory()->create();
    $video = CommitVideo::query()->create(['user_id' => $user->id, 'size' => 9]);
    Storage::put($video->path('source'), str_repeat('x', 5000));

    $this->actingAs($user)->postJson(route('commit-video-processing.store', $video))->assertOk();
    Queue::pushed(ProcessCommitVideo::class)->sole()->handle(resolve(CommitVideos::class));

    expect($video->refresh()->status)->toBe(CommitVideoStatus::Failed);
    Storage::assertMissing($video->path('source'));
});

it('enforces upload limits and ownership', function (): void {
    $owner = User::factory()->create();
    $video = CommitVideo::query()->create(['user_id' => $owner->id, 'size' => 1]);
    $this->postJson(route('commit-video.store'), ['size' => 1])->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $this->postJson(route('commit-video.store'), ['size' => CommitVideo::MAX_BYTES + 1])->assertUnprocessable();
    $this->postJson(route('commit-video.store'), ['size' => 0])->assertUnprocessable();
    $this->getJson(route('commit-video.show', $video))->assertNotFound();
    $this->postJson(route('commit-video-processing.store', $video))->assertNotFound();
    $this->deleteJson(route('commit-video.destroy', $video))->assertNotFound();
    $this->putJson(route('commit-video-upload.update', $video))->assertNotFound();
});

it('requires manual approval even for an admin and never lets AI approve a video', function (): void {
    config(['app.admins' => ['admin']]);
    $user = User::factory()->create(['username' => 'admin']);
    $video = CommitVideo::query()->create(['user_id' => $user->id, 'size' => 1, 'status' => CommitVideoStatus::Ready]);
    $this->actingAs($user)->post(route('commit.store'), ['video_id' => $video->id, 'video_description' => 'A new feature.'])->assertRedirect();
    $commit = $user->commits()->sole();
    expect($commit->status)->toBe(CommitStatus::Pending)->and($commit->body)->toBe('')
        ->and($commit->isBeingChecked())->toBeFalse()->and($video->refresh()->commit_id)->toBe($commit->id)
        ->and($video->description)->toBe('A new feature.');
    Queue::assertNotPushed(ModerateCommit::class);
    resolve(UpdateCommitAiDecision::class)->handle($commit);
    new ModerateCommit($commit)->handle(resolve(UpdateCommitAiDecision::class), resolve(UpdateCommitStatuses::class));
    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
    $this->deleteJson(route('commit-video.destroy', $video))->assertNotFound();
    $this->postJson(route('commit.store'), ['video_id' => $video->id])->assertUnprocessable();
});

it('rejects unfinished and foreign videos and incompatible attachments', function (): void {
    $user = User::factory()->create();
    $video = CommitVideo::query()->create(['user_id' => $user->id, 'size' => 1, 'status' => CommitVideoStatus::Processing]);
    $this->actingAs($user)->postJson(route('commit.store'), ['video_id' => $video->id])->assertUnprocessable();
    $video->update(['status' => CommitVideoStatus::Ready]);
    $this->actingAs(User::factory()->create())->postJson(route('commit.store'), ['video_id' => $video->id])->assertUnprocessable();
    $this->actingAs($user);
    $parent = Commit::factory()->create();
    foreach ([['images' => [UploadedFile::fake()->image('image.jpg')]], ['poll' => ['options' => ['One', 'Two'], 'duration' => 60]], ['quoted_commit_id' => $parent->id], ['parent_id' => $parent->id], ['scheduled_at' => now()->addHour()->toIso8601String()]] as $attachment) {
        $this->postJson(route('commit.store'), ['body' => 'Look', 'video_id' => $video->id, ...$attachment])->assertUnprocessable();
    }

    $this->postJson(route('commit-reply.store', $parent), ['body' => 'Look', 'video_id' => $video->id])->assertUnprocessable();
    $this->postJson(route('scheduled-commit.store'), ['body' => 'Look', 'video_id' => $video->id, 'scheduled_at' => now()->addHour()->toIso8601String()])->assertUnprocessable();
    expect($user->commits()->count())->toBe(0);
});

it('authorizes playback and posters before issuing a short lived storage URL', function (): void {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Storage::disk()->buildTemporaryUrlsUsing(fn (string $path): string => 'https://storage.example/'.$path);
    $commit = Commit::factory()->pending()->create();
    $video = CommitVideo::query()->create(['user_id' => $commit->user_id, 'status' => CommitVideoStatus::Processing, 'size' => 1]);
    $url = route('commit-video-playback.show', $video);
    $this->actingAs($commit->user)->get($url)->assertNotFound();
    $video->update(['status' => CommitVideoStatus::Ready]);
    $this->get($url)->assertRedirect('https://storage.example/'.$video->path('video.mp4'));
    $this->get($url.'?poster=1')->assertRedirect('https://storage.example/'.$video->path('poster.jpg'));
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    $this->app['auth']->forgetGuards();
    $this->get($url)->assertNotFound();
    $video->update(['commit_id' => $commit->id]);
    $this->get($url)->assertNotFound();
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    $this->actingAs($commit->user)->get($url)->assertRedirect('https://storage.example/'.$video->path('video.mp4'));
    $this->get($url.'?poster=1')->assertRedirect('https://storage.example/'.$video->path('poster.jpg'));
    $this->app['auth']->forgetGuards();
    $signed = URL::temporarySignedRoute('commit-video-playback.show', now()->addMinutes(5), ['commitVideo' => $video]);
    $this->get($signed)->assertRedirect();
    $commit->update(['status' => CommitStatus::Approved]);
    $this->get($url)->assertRedirect();
    $this->getJson(route('api-commit.show', $commit))->assertOk()
        ->assertJsonPath('data.video.url', $url)
        ->assertJsonPath('data.video.poster_url', route('commit-video-playback.show', [$video, 'poster' => true]));
    $commit->delete();
    $this->get($url)->assertNotFound();
    $this->get($signed)->assertNotFound();
});

it('gives the signed API author expiring playback access without exposing private storage paths', function (): void {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Storage::disk()->buildTemporaryUrlsUsing(fn (string $path): string => 'https://storage.example/'.$path);
    $commit = Commit::factory()->pending()->create();
    $video = CommitVideo::factory()->create(['user_id' => $commit->user_id, 'commit_id' => $commit->id, 'status' => CommitVideoStatus::Ready, 'width' => 96, 'height' => 64, 'duration' => 1]);
    $response = signedApiRequest(apiKeyPairFor($commit->user), 'GET', route('api-commit.show', $commit))->assertOk()
        ->assertJsonPath('data.video.id', $video->id)->assertJsonMissingPath('data.video.user_id')
        ->assertJsonMissingPath('data.video.size');
    $this->app['auth']->forgetGuards();
    $this->getJson(route('api-commit.show', $commit))->assertNotFound();
    $this->get($response->json('data.video.url'))->assertRedirect();
    $this->get($response->json('data.video.poster_url'))->assertRedirect();
    $this->travel(6)->minutes();
    $this->get($response->json('data.video.url'))->assertNotFound();
});

it('serves private local video byte ranges for seeking and replay', function (): void {
    $video = CommitVideo::factory()->create(['status' => CommitVideoStatus::Ready]);
    Storage::put($video->path('video.mp4'), '0123456789');
    Storage::put($video->path('poster.jpg'), 'poster');
    $url = route('commit-video-playback.show', $video);
    $this->get($url, ['Range' => 'bytes=2-5'])->assertNotFound();
    $response = $this->actingAs(User::query()->findOrFail($video->user_id))->get($url, ['Range' => 'bytes=2-5'])
        ->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/10')
        ->assertHeader('Content-Length', '4')->assertHeader('Content-Type', 'video/mp4');
    ob_start();
    $response->baseResponse->sendContent();
    expect(ob_get_clean())->toBe('2345');
    $this->get($url.'?poster=1')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});

it('signs direct S3 uploads without asking browsers to set forbidden headers', function (): void {
    config(['filesystems.default' => 's3', 'filesystems.disks.s3.key' => 'example-key', 'filesystems.disks.s3.secret' => 'example-secret', 'filesystems.disks.s3.bucket' => 'private-test', 'filesystems.disks.s3.region' => 'us-east-1']);
    $response = $this->actingAs(User::factory()->create())->postJson(route('commit-video.store'), ['size' => CommitVideo::MAX_BYTES])->assertCreated()
        ->assertJsonPath('upload.method', 'PUT')->assertJsonMissingPath('upload.headers.Host')->assertJsonMissingPath('upload.headers.Content-Length');
    $url = $response->json('upload.url');
    expect($url)->toContain('video-uploads/', 'X-Amz-Signature=');
    parse_str(str($url)->after('?')->value(), $query);
    expect(strtotime($query['X-Amz-Date']) + (int) $query['X-Amz-Expires'])->toBe(now()->addMinutes(15)->timestamp);
});
