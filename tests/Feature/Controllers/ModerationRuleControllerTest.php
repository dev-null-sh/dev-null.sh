<?php

declare(strict_types=1);

use App\Enums\EvaluationDecision;
use App\Enums\ModerationRuleAction;
use App\Enums\ModerationRuleMode;
use App\Enums\ModerationStrictness;
use App\Models\CommitEvaluation;
use App\Models\ModerationRule;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('renders the rules in order with the templates, status and stats', function (): void {
    config(['services.typesafe.key' => 'test-key', 'services.typesafe.enabled' => false]);

    $admin = User::factory()->create(['username' => 'admin']);
    ModerationRule::factory()->create(['name' => 'Second', 'position' => 1]);
    ModerationRule::factory()->create(['name' => 'First', 'position' => 0]);
    CommitEvaluation::factory()->decided(EvaluationDecision::Rejected)->create();

    $response = $this->actingAs($admin)->get(route('moderation-rule.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderation/rules')
            ->where('rules.0.name', 'First')
            ->where('rules.1.name', 'Second')
            ->where('rules.0.mode', 'live')
            ->has('templates', 8)
            ->where('templates.0.name', 'Spam and self-promotion')
            ->where('presets.strict.reject', ['act' => 80, 'review' => 30])
            ->where('presets.balanced.approve', ['act' => 90, 'review' => null])
            ->where('presets.lenient.review', ['act' => null, 'review' => 70])
            ->where('maxRules', 25)
            ->where('status', ['isConfigured' => true, 'isEnabled' => false, 'model' => 'jev-1.13.0'])
            ->where('stats', ['approved' => 0, 'rejected' => 1, 'review' => 0, 'failed' => 0, 'overturned' => 1]));
});

it('adds a rule in test mode at the end', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    ModerationRule::factory()->create(['position' => 3]);

    $response = $this->actingAs($admin)
        ->fromRoute('moderation-rule.index')
        ->post(route('moderation-rule.store'), [
            'name' => 'Recruiter spam',
            'matches' => 'Job offers sent to everyone, unrelated to the post.',
            'does_not_match' => 'Sharing an opening at your own company.',
            'action' => 'reject',
            'strictness' => 'lenient',
            'rejection_message' => 'Please post job offers on a job board.',
        ]);

    $response->assertRedirectToRoute('moderation-rule.index');

    expect(ModerationRule::query()->where('name', 'Recruiter spam')->sole())
        ->mode->toBe(ModerationRuleMode::Test)
        ->action->toBe(ModerationRuleAction::Reject)
        ->strictness->toBe(ModerationStrictness::Lenient)
        ->position->toBe(4)
        ->rejection_message->toBe('Please post job offers on a job board.');
});

it('adds the first rule at the top', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    $this->actingAs($admin)->post(route('moderation-rule.store'), [
        'name' => 'Off-topic',
        'matches' => 'Not about software.',
        'action' => 'review',
        'strictness' => 'balanced',
        'mode' => 'off',
    ]);

    expect(ModerationRule::query()->sole())
        ->position->toBe(0)
        ->mode->toBe(ModerationRuleMode::Off)
        ->does_not_match->toBeNull();
});

it('validates new rules', function (array $data, string $error): void {
    $admin = User::factory()->create(['username' => 'admin']);

    $response = $this->actingAs($admin)->post(route('moderation-rule.store'), [
        'name' => 'Spam',
        'matches' => 'Advertising.',
        'action' => 'reject',
        'strictness' => 'balanced',
        ...$data,
    ]);

    $response->assertSessionHasErrors($error);

    expect(ModerationRule::query()->count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'a long name' => [['name' => str_repeat('a', 81)], 'name'],
    'no matches' => [['matches' => ''], 'matches'],
    'long matches' => [['matches' => str_repeat('a', 501)], 'matches'],
    'long does not match' => [['does_not_match' => str_repeat('a', 501)], 'does_not_match'],
    'an unknown action' => [['action' => 'ban'], 'action'],
    'an unknown strictness' => [['strictness' => 'paranoid'], 'strictness'],
    'an unknown mode' => [['mode' => 'shadow'], 'mode'],
    'a long rejection message' => [['rejection_message' => str_repeat('a', 281)], 'rejection_message'],
]);

it('allows up to 25 rules', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    ModerationRule::factory()->count(25)->create();

    $response = $this->actingAs($admin)->post(route('moderation-rule.store'), [
        'name' => 'One too many',
        'matches' => 'Anything.',
        'action' => 'review',
        'strictness' => 'balanced',
    ]);

    $response->assertSessionHasErrors(['name' => 'You can have up to 25 rules. Delete one first.']);

    expect(ModerationRule::query()->count())->toBe(25);
});

it('switches the mode or strictness of a rule alone', function (array $data, string $attribute, BackedEnum $value): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $rule = ModerationRule::factory()->testing()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation-rule.index')
        ->patch(route('moderation-rule.update', $rule), $data);

    $response->assertRedirectToRoute('moderation-rule.index');

    expect($rule->refresh())
        ->{$attribute}->toBe($value)
        ->name->toBe('Spam and self-promotion');
})->with([
    'live' => [['mode' => 'live'], 'mode', ModerationRuleMode::Live],
    'off' => [['mode' => 'off'], 'mode', ModerationRuleMode::Off],
    'strict' => [['strictness' => 'strict'], 'strictness', ModerationStrictness::Strict],
]);

it('edits a rule', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $rule = ModerationRule::factory()->create();

    $this->actingAs($admin)->patch(route('moderation-rule.update', $rule), [
        'name' => 'Spam',
        'matches' => 'Selling things.',
        'does_not_match' => null,
        'action' => 'review',
        'strictness' => 'lenient',
        'rejection_message' => null,
    ]);

    expect($rule->refresh())
        ->name->toBe('Spam')
        ->matches->toBe('Selling things.')
        ->does_not_match->toBeNull()
        ->action->toBe(ModerationRuleAction::Review)
        ->rejection_message->toBeNull();
});

it('validates rule edits', function (array $data, string $error): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $rule = ModerationRule::factory()->create();

    $response = $this->actingAs($admin)->patch(route('moderation-rule.update', $rule), $data);

    $response->assertSessionHasErrors($error);
})->with([
    'an empty name' => [['name' => ''], 'name'],
    'an unknown mode' => [['mode' => 'shadow'], 'mode'],
]);

it('deletes a rule', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $rule = ModerationRule::factory()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation-rule.index')
        ->delete(route('moderation-rule.destroy', $rule));

    $response->assertRedirectToRoute('moderation-rule.index');

    expect(ModerationRule::query()->count())->toBe(0);
});

it('may only be managed by admins', function (string $method, Closure $url): void {
    $rule = ModerationRule::factory()->create();

    $response = $this->actingAs(User::factory()->create())->{$method}($url($rule), ['mode' => 'off']);

    $response->assertForbidden();

    expect($rule->refresh()->mode)->toBe(ModerationRuleMode::Live);
})->with([
    'index' => ['get', fn (): string => route('moderation-rule.index')],
    'store' => ['post', fn (): string => route('moderation-rule.store')],
    'update' => ['patch', fn (ModerationRule $rule): string => route('moderation-rule.update', $rule)],
    'destroy' => ['delete', fn (ModerationRule $rule): string => route('moderation-rule.destroy', $rule)],
    'starter' => ['post', fn (): string => route('starter-moderation-rule.store')],
    'position' => ['put', fn (ModerationRule $rule): string => route('moderation-rule-position.update', $rule)],
    'preview' => ['post', fn (): string => route('moderation-rule-preview.store')],
]);
