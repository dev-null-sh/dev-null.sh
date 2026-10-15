<?php

declare(strict_types=1);

use App\Models\CommitEvaluation;
use App\Models\ModerationRule;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['app.admins' => ['admin'], 'services.typesafe.key' => 'test-key', 'services.typesafe.enabled' => false]);
});

it('tries the live and test rules on a draft without moderating anything', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $harassment = ModerationRule::factory()->create(['name' => 'Harassment']);
    $spam = ModerationRule::factory()->testing()->create(['name' => 'Spam']);
    ModerationRule::factory()->off()->create(['name' => 'Off-topic']);

    Http::fake(['api.typesafe.ai/*' => Http::response(jevAnswers([
        "rule_{$harassment->id}" => 0.69,
        "rule_{$spam->id}" => 0.97,
    ]))]);

    $response = $this->actingAs($admin)->postJson(route('moderation-rule-preview.store'), [
        'body' => 'Nobody asked. Delete it.',
        'parent_body' => 'Just shipped my first package!',
    ]);

    $response->assertOk()
        ->assertJson([
            'decision' => 'review',
            'decided_by' => 'Harassment',
            'test_decision' => 'rejected',
            'test_decided_by' => 'Spam',
            'model' => 'jev-1.13.0',
            'results' => [
                ['rule_id' => $harassment->id, 'name' => 'Harassment', 'mode' => 'live', 'probability' => 0.69, 'outcome' => 'review'],
                ['rule_id' => $spam->id, 'name' => 'Spam', 'mode' => 'test', 'probability' => 0.97, 'outcome' => 'reject'],
            ],
        ])
        ->assertJsonCount(2, 'results');

    Http::assertSent(fn (Request $request): bool => $request['state'] === [
        'parent' => ['body' => 'Just shipped my first package!'],
        'commit' => ['body' => 'Nobody asked. Delete it.'],
    ]);

    expect(CommitEvaluation::query()->count())->toBe(0);
});

it('tries a draft without a parent', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $friendly = ModerationRule::factory()->approving()->create();

    Http::fake(['api.typesafe.ai/*' => Http::response(jevAnswers(["rule_{$friendly->id}" => 0.98]))]);

    $response = $this->actingAs($admin)->postJson(route('moderation-rule-preview.store'), ['body' => 'Shipped it', 'parent_body' => '']);

    $response->assertOk()->assertJson(['decision' => 'approved', 'decided_by' => 'Friendly dev content']);

    Http::assertSent(fn (Request $request): bool => $request['state'] === ['commit' => ['body' => 'Shipped it']]);
});

it('needs an api key and an active rule', function (Closure $setUp, string $message): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $setUp();

    Http::fake();

    $response = $this->actingAs($admin)->postJson(route('moderation-rule-preview.store'), ['body' => 'Shipped it']);

    $response->assertUnprocessable()->assertJsonValidationErrors(['body' => $message]);

    Http::assertNothingSent();
})->with([
    'no key' => [function (): void {
        config(['services.typesafe.key' => null]);
        ModerationRule::factory()->create();
    }, 'Set TYPESAFE_API_KEY to try the rules.'],
    'no active rule' => [fn (): ModerationRule => ModerationRule::factory()->off()->create(), 'Put a rule in Test or Live mode to try it.'],
]);

it('says when jev could not answer', function (): void {
    Exceptions::fake();

    $admin = User::factory()->create(['username' => 'admin']);
    ModerationRule::factory()->create();

    Http::fake(['api.typesafe.ai/*' => Http::response(['detail' => 'Overloaded'], 529)]);

    $response = $this->actingAs($admin)->postJson(route('moderation-rule-preview.store'), ['body' => 'Shipped it']);

    $response->assertServiceUnavailable()->assertJson(['message' => 'Jev could not answer. Try again in a moment.']);

    Exceptions::assertReportedCount(1);
});

it('validates the draft', function (array $data, string $error): void {
    $admin = User::factory()->create(['username' => 'admin']);

    $response = $this->actingAs($admin)->postJson(route('moderation-rule-preview.store'), $data);

    $response->assertJsonValidationErrors($error);
})->with([
    'no body' => [['body' => ''], 'body'],
    'a long body' => [['body' => str_repeat('a', 281)], 'body'],
    'a long parent' => [['body' => 'Hi', 'parent_body' => str_repeat('a', 281)], 'parent_body'],
]);
