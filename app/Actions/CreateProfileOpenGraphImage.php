<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\GitHubProvider;
use App\Models\Organization;
use App\Models\Profile;
use App\Services\OpenGraphCanvas;
use Illuminate\Support\Facades\Cache;

final readonly class CreateProfileOpenGraphImage
{
    private const int AVATAR_SIZE = 152;

    private const int BIO_SIZE = 34;

    private const int BIO_LINE_HEIGHT = 50;

    private const int BIO_MAX_LINES = 3;

    public function __construct(private GitHubProvider $github)
    {
        //
    }

    /**
     * The card of a profile no user owns: an organization's, or an unclaimed developer's.
     */
    public function handle(Organization|Profile $owner): string
    {
        return Cache::remember(
            "open-graph-image:profile:{$owner->id}:{$owner->openGraphImageVersion()}",
            now()->addHour(),
            fn (): string => $this->render($owner),
        );
    }

    /**
     * Drawn like a user's card, in the organization's lane, /dev/null's blue until its admins choose one. An unclaimed
     * developer's is grey, with no lane of their own yet, and says they aren't here, where a user's shows their stats.
     */
    private function render(Organization|Profile $owner): string
    {
        $canvas = new OpenGraphCanvas($this->github);
        $lane = match (true) {
            $owner instanceof Profile => OpenGraphCanvas::INK_MUTED,
            $owner->lane() === null => OpenGraphCanvas::PRIMARY,
            default => $canvas->lane($owner->lane()),
        };
        $width = OpenGraphCanvas::CONTENT_RIGHT - OpenGraphCanvas::CONTENT_X;

        $bio = $canvas->clamp(
            $canvas->wrap($canvas->clean((string) $owner->bio), 'Geist-Regular', self::BIO_SIZE, $width),
            self::BIO_MAX_LINES, 'Geist-Regular', self::BIO_SIZE, $width,
        );

        $blockHeight = self::AVATAR_SIZE + ($bio === [] ? 0 : 48 + count($bio) * self::BIO_LINE_HEIGHT);
        $top = max(56, intdiv(OpenGraphCanvas::FOOTER_RULE_Y - $blockHeight, 2));

        $canvas->rail($top + intdiv(self::AVATAR_SIZE, 2), $lane);
        $canvas->avatar($owner, OpenGraphCanvas::CONTENT_X + 10, $top, self::AVATAR_SIZE, $lane);

        $textX = OpenGraphCanvas::CONTENT_X + 10 + self::AVATAR_SIZE + 10 + 36;
        $textWidth = OpenGraphCanvas::CONTENT_RIGHT - $textX;

        $canvas->text($canvas->truncate($canvas->name($owner), 'Geist-SemiBold', 60, $textWidth), 'Geist-SemiBold', 60, $textX, $top + 74, OpenGraphCanvas::INK);
        $canvas->handle($owner, 32, $textX, $top + 124, $textWidth, $lane);

        $bioTop = $top + self::AVATAR_SIZE + 48;

        if ($bio !== []) {
            $canvas->node($bioTop + intdiv(self::BIO_LINE_HEIGHT, 2) - 2, $lane);
        }

        foreach ($bio as $index => $line) {
            $canvas->text($line, 'Geist-Regular', self::BIO_SIZE, OpenGraphCanvas::CONTENT_X, $bioTop + 36 + $index * self::BIO_LINE_HEIGHT, OpenGraphCanvas::INK);
        }

        $canvas->footer();
        $canvas->text($owner instanceof Profile ? 'Not on /dev/null yet' : 'Organization', 'Geist-Regular', 30, OpenGraphCanvas::CONTENT_X, OpenGraphCanvas::FOOTER_BASELINE, OpenGraphCanvas::INK_MUTED);

        return $canvas->png();
    }
}
