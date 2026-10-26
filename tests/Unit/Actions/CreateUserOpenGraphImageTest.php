<?php

declare(strict_types=1);

use App\Actions\CreateUserOpenGraphImage;
use App\Models\User;
use App\Services\OpenGraphCanvas;
use Illuminate\Support\Facades\Cache;

it('falls back to the handle when the name has nothing Geist can draw', function (): void {
    $user = User::factory()->create(['handle' => 'octocat', 'name' => 'octocat', 'avatar_url' => null, 'bio' => null]);
    $action = resolve(CreateUserOpenGraphImage::class);

    $withHandle = $action->handle($user);

    Cache::flush();
    $user->profile->name = '🚀🔥';

    expect($action->handle($user))->toBe($withHandle);
});

it('fits bios that would overflow the card', function (string $bio): void {
    $user = User::factory()->create(['avatar_url' => null, 'bio' => $bio]);

    $image = resolve(CreateUserOpenGraphImage::class)->handle($user);

    expect(array_slice((array) getimagesizefromstring($image), 0, 2))->toBe([1200, 630]);
})->with([
    'an unbroken word wider than the card' => ['See '.str_repeat('supercalifragilistic', 12)],
    'more lines than the card holds' => [str_repeat('Writing PHP on purpose and shipping it before lunch. ', 4)],
]);

it('draws the rail in the custom color of the user', function (): void {
    $user = User::factory()->create(['avatar_url' => null, 'bio' => null, 'lane_color' => '#1a2b3c']);

    $image = imagecreatefromstring(resolve(CreateUserOpenGraphImage::class)->handle($user));

    expect($image)->toBeInstanceOf(GdImage::class)
        ->and(imagecolorat($image, OpenGraphCanvas::RAIL_X, 440))->toBe(0x1A2B3C);
});
