<?php

declare(strict_types=1);

use App\Actions\RenderUserOpenGraphImage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('falls back to the username when the name has nothing Geist can draw', function (): void {
    $user = User::factory()->create(['username' => 'octocat', 'name' => 'octocat', 'avatar_url' => null, 'bio' => null]);
    $action = resolve(RenderUserOpenGraphImage::class);

    $withUsername = $action->handle($user);

    Cache::flush();
    $user->name = '🚀🔥';

    expect($action->handle($user))->toBe($withUsername);
});

it('fits bios that would overflow the card', function (string $bio): void {
    $user = User::factory()->create(['avatar_url' => null, 'bio' => $bio]);

    $image = resolve(RenderUserOpenGraphImage::class)->handle($user);

    expect(array_slice((array) getimagesizefromstring($image), 0, 2))->toBe([1200, 630]);
})->with([
    'an unbroken word wider than the card' => ['See '.str_repeat('supercalifragilistic', 20)],
    'more lines than the card holds' => [str_repeat('Writing PHP on purpose and shipping it before lunch. ', 6)],
]);
