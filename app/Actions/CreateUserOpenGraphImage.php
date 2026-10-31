<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use App\Services\OpenGraphCanvas;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final readonly class CreateUserOpenGraphImage
{
    private const int AVATAR_SIZE = 152;

    private const int BIO_SIZE = 34;

    private const int BIO_LINE_HEIGHT = 50;

    private const int BIO_MAX_LINES = 3;

    public function __construct(private GitHubProvider $github)
    {
        //
    }

    public function handle(User $user): string
    {
        $user->loadPublicCounts();

        return Cache::remember(
            "open-graph-image:user:{$user->id}:{$user->openGraphImageVersion()}",
            now()->addHour(),
            fn (): string => $this->render($user),
        );
    }

    private function render(User $user): string
    {
        $canvas = new OpenGraphCanvas($this->github);
        $lane = $canvas->lane($user->lane());
        $width = OpenGraphCanvas::CONTENT_RIGHT - OpenGraphCanvas::CONTENT_X;

        $bio = $canvas->clamp(
            $canvas->wrap($canvas->clean((string) $user->bio), 'Geist-Regular', self::BIO_SIZE, $width),
            self::BIO_MAX_LINES, 'Geist-Regular', self::BIO_SIZE, $width,
        );

        $blockHeight = self::AVATAR_SIZE + ($bio === [] ? 0 : 48 + count($bio) * self::BIO_LINE_HEIGHT);
        $top = max(56, intdiv(OpenGraphCanvas::FOOTER_RULE_Y - $blockHeight, 2));

        $canvas->rail($top + intdiv(self::AVATAR_SIZE, 2), $lane);
        $canvas->avatar($user, OpenGraphCanvas::CONTENT_X + 10, $top, self::AVATAR_SIZE, $lane);

        $textX = OpenGraphCanvas::CONTENT_X + 10 + self::AVATAR_SIZE + 10 + 36;
        $textWidth = OpenGraphCanvas::CONTENT_RIGHT - $textX;

        $canvas->text($canvas->truncate($canvas->name($user), 'Geist-SemiBold', 60, $textWidth), 'Geist-SemiBold', 60, $textX, $top + 74, OpenGraphCanvas::INK);
        $canvas->handle($user, 32, $textX, $top + 124, $textWidth, $lane);

        $bioTop = $top + self::AVATAR_SIZE + 48;

        if ($bio !== []) {
            $canvas->node($bioTop + intdiv(self::BIO_LINE_HEIGHT, 2) - 2, $lane);
        }

        foreach ($bio as $index => $line) {
            $canvas->text($line, 'Geist-Regular', self::BIO_SIZE, OpenGraphCanvas::CONTENT_X, $bioTop + 36 + $index * self::BIO_LINE_HEIGHT, OpenGraphCanvas::INK);
        }

        $canvas->footer();

        $x = OpenGraphCanvas::CONTENT_X;

        foreach ([
            Str::plural('commit', $user->commits_count) => $user->commits_count,
            'following' => $user->following_count,
            Str::plural('follower', $user->followers_count) => $user->followers_count,
        ] as $label => $count) {
            $x = $canvas->count($count, $label, $x);
        }

        return $canvas->png();
    }
}
