<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Services\OpenAI;
use Illuminate\Support\Facades\Storage;
use Intervention\Gif\Decoder as GifDecoder;
use Intervention\Gif\DisposalMethod;

final readonly class UpdateCommitAiDecision
{
    /**
     * A category scoring this much leaves no doubt, so the commit is rejected without waiting for a moderator.
     */
    private const float AUTO_REJECT_THRESHOLD = 0.95;

    /**
     * How many frames of a GIF are checked, spread evenly from its first to its last.
     */
    private const int GIF_FRAMES = 10;

    public function __construct(
        private OpenAI $openAI,
        private UpdateCommitStatuses $updateCommitStatuses,
    ) {
        //
    }

    /**
     * Asks the AI whether the commit, images included, should be approved. The decision is mostly a hint for
     * moderators: a clean commit is approved unless its author has recent reports, and it is rejected,
     * without a reason, when the AI is all but certain. Failures, such as timeouts or rate limits, are thrown, so the
     * caller can try again later.
     */
    public function handle(Commit $commit): void
    {
        $images = array_values($commit->images->flatMap(
            $this->dataUrls(...),
        )->all());

        $scores = $this->openAI->moderate($commit->body, $images);

        $categories = array_keys($scores);

        if ($commit->body !== '' && $this->openAI->isHarmful($commit->body)) {
            $categories[] = 'harmful-text';
        }

        if ($images !== [] && $this->openAI->showsMinors($images)) {
            $categories[] = 'minor-in-image';
        }

        $commit->update([
            'ai_decision' => $categories === [] ? CommitStatus::Approved : CommitStatus::Rejected,
            'ai_categories' => $categories,
        ]);

        if ($scores !== [] && max($scores) >= self::AUTO_REJECT_THRESHOLD) {
            $this->updateCommitStatuses->handle([$commit->id], CommitStatus::Rejected, onlyPending: true);
        }

        if ($categories === [] && $commit->user !== null && ! $commit->user->hasRecentReports()) {
            $this->updateCommitStatuses->handle([$commit->id], CommitStatus::Approved, onlyPending: true);
        }
    }

    /**
     * The AI does not take animated images, so a GIF is checked by its frames instead.
     *
     * @return list<string>
     */
    private function dataUrls(CommitImage $image): array
    {
        $bytes = (string) Storage::get($image->path);

        if ($image->mimeType() !== 'image/gif') {
            return ['data:image/webp;base64,'.base64_encode($bytes)];
        }

        return array_map(
            fn (string $frame): string => 'data:image/png;base64,'.base64_encode($frame),
            $this->gifFrames($bytes),
        );
    }

    /**
     * Most frames only redraw part of the picture, so each is drawn over the ones before it, as a browser shows it.
     *
     * @return list<string>
     */
    private function gifFrames(string $bytes): array
    {
        $gif = GifDecoder::decode($bytes);
        $frames = $gif->frames();
        $last = count($frames) - 1;
        $checked = array_map(fn (int $step): int => (int) round($step * $last / (self::GIF_FRAMES - 1)), range(0, self::GIF_FRAMES - 1));

        // A GIF may claim an empty screen, but its frames still draw onto at least one pixel.
        $canvas = imagecreatetruecolor(max(1, $gif->logicalScreenDescriptor()->width()), max(1, $gif->logicalScreenDescriptor()->height()));
        $pngs = [];

        foreach ($frames as $index => $frame) {
            $descriptor = $frame->imageDescriptor();
            $disposal = $frame->graphicControlExtension()?->disposalMethod();
            $previous = $disposal === DisposalMethod::PREVIOUS ? imagecrop($canvas, ['x' => 0, 'y' => 0, 'width' => imagesx($canvas), 'height' => imagesy($canvas)]) : false;

            $image = imagecreatefromstring((clone $gif)->setFrames([$frame])->encode());
            assert($image !== false);
            imagecopy($canvas, $image, $descriptor->left(), $descriptor->top(), 0, 0, imagesx($image), imagesy($image));

            if (in_array($index, $checked, true)) {
                ob_start();
                imagepng($canvas);
                $pngs[] = (string) ob_get_clean();
            }

            if ($disposal === DisposalMethod::BACKGROUND) {
                imagefilledrectangle($canvas, $descriptor->left(), $descriptor->top(), $descriptor->left() + $descriptor->width() - 1, $descriptor->top() + $descriptor->height() - 1, 0);
            } elseif ($previous !== false) {
                $canvas = $previous;
            }
        }

        return $pngs;
    }
}
