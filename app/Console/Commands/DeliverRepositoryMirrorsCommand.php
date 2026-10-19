<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RepositoryMirror;
use App\Models\RepositoryMirrorEvent;
use App\Services\GitHubRepositoryMirror;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('mirrors:deliver {--limit=100 : Maximum events per repository}')]
#[Description('Deliver pending repository mirror events in order; failures retry on the next run')]
final class DeliverRepositoryMirrorsCommand extends Command
{
    public function handle(GitHubRepositoryMirror $github): int
    {
        $failed = false;
        foreach (RepositoryMirror::query()->lazyById() as $candidate) {
            $limit = max(1, min(1000, (int) $this->option('limit')));
            for ($delivered = 0; $delivered < $limit; $delivered++) {
                // Release the connection lock between records so settings and reactions can interleave.
                $more = DB::transaction(function () use ($candidate, $github, &$failed): bool {
                    $mirror = RepositoryMirror::query()->whereKey($candidate->id)->lockForUpdate()->first();
                    if (! $mirror instanceof RepositoryMirror) {
                        return false;
                    }
                    $event = RepositoryMirrorEvent::query()->where('repository_mirror_id', $mirror->id)->whereNull('delivered_at')->orderBy('id')->first();
                    if (! $event instanceof RepositoryMirrorEvent) {
                        return false;
                    }
                    try {
                        $github->deliver($mirror, $event);
                    } catch (Throwable) {
                        // Never persist exception bodies: tokens or private content can be present in HTTP diagnostics.
                        $mirror->forceFill(['last_error' => 'Delivery paused. Check repository access, visibility and token, then retry.'])->save();
                        $this->error('Delivery failed for mirror '.$mirror->id.'. Pending records are retained.');
                        $failed = true;

                        return false;
                    }
                    $event->forceFill(['delivered_at' => now()])->save();
                    $mirror->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

                    return true;
                });
                if (! $more) {
                    break;
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
