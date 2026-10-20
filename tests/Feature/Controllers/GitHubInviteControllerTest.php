<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('lists the people followed on github who are not on devnull yet', function (): void {
    $user = User::factory()->create();
    $member = User::factory()->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => $user->username]),
        "api.github.com/users/{$user->username}/following*" => Http::response([
            ['id' => (int) $member->github_id, 'login' => $member->username, 'avatar_url' => 'https://avatars.githubusercontent.com/u/1'],
            ['id' => 424242, 'login' => 'octocat', 'avatar_url' => 'https://avatars.githubusercontent.com/u/424242?v=4'],
        ]),
    ]);

    $this->actingAs($user)->get(route('github-invite.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('invite/index')
        ->missing('invitees')
        ->loadDeferredProps(fn ($reload) => $reload
            ->has('invitees', 1)
            ->where('invitees.0', [
                'id' => '424242',
                'login' => 'octocat',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/424242?v=4',
            ])));
});

it('tells the page when github did not answer', function (): void {
    $user = User::factory()->create();

    Http::fake(["api.github.com/user/{$user->github_id}" => Http::response(status: 502)]);

    $this->actingAs($user)->get(route('github-invite.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($reload) => $reload->where('invitees', null)));
});

it('keeps the invite page for signed in developers', function (): void {
    $this->get('/invite')->assertRedirectToRoute('home');
});
