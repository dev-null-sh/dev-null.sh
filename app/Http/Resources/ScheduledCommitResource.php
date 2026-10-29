<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ScheduledCommit;
use App\Services\CommitBodies;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A commit the user queued, as the web's Scheduled page shows it. Once submitted, its body lives on the commit and
 * only the status says what became of it: scheduled, the commit's moderation status, or deleted.
 *
 * @mixin ScheduledCommit
 */
final class ScheduledCommitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...($this->submitted_at === null && $this->body !== null
                ? ['body' => $this->body, ...resolve(CommitBodies::class)->segments($this->body)]
                : ['body' => null, 'body_segments' => [], 'preview_segments' => null]),
            'poll_options' => $this->poll['options'] ?? null,
            'image_count' => count($this->images ?? []),
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'status' => $this->submitted_at === null ? 'scheduled' : ($this->commit === null || $this->commit->trashed() ? 'deleted' : $this->commit->status->value),
        ];
    }
}
