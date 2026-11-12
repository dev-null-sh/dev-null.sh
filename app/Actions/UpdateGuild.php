<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Queries\AdminsQuery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class UpdateGuild
{
    public function __construct(private CreateImageFiles $images, private DeleteStoredFiles $deleteFiles, private AdminsQuery $admins)
    {
        //
    }

    public function handle(Guild $guild, string $description, string $emoji, ?UploadedFile $icon, bool $removeIcon): void
    {
        DB::transaction(function () use ($guild, $description, $emoji, $icon, $removeIcon): void {
            $guild = Guild::query()->whereKey($guild->id)->lockForUpdate()->firstOrFail();
            $path = $guild->icon_path;

            if ($icon instanceof UploadedFile || $removeIcon) {
                $this->deleteFiles->retain($path === null ? [] : [$path]);
                $path = $icon instanceof UploadedFile ? $this->images->handle([$icon], directory: 'guild-icons')[0]['path'] : null;
            }

            $guild->update(['description' => $description, 'emoji' => $emoji, 'icon_path' => $path]);
            event(new UsersUpdated($this->admins->ids(), UserUpdate::Guilds));
        });
    }
}
