<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUserProfileDetails
{
    public function __construct(
        private CreateImageFiles $createImageFiles,
        private DeleteStoredFiles $deleteStoredFiles,
    ) {
        //
    }

    /**
     * @param  array{
     *     dev_null_username: string|null,
     *     name: string,
     *     bio: string|null,
     *     company: string|null,
     *     location: string|null,
     *     website: string|null,
     *     sponsors_url: string|null
     * }  $attributes
     */
    public function handle(User $user, array $attributes, ?UploadedFile $avatar): void
    {
        DB::transaction(function () use ($user, $attributes, $avatar): void {
            $profile = [
                'name' => $attributes['name'],
                'bio' => $attributes['bio'],
                'location' => $attributes['location'],
                'website' => $attributes['website'],
            ];
            $userAttributes = [
                'dev_null_username' => $attributes['dev_null_username'],
                'company' => $attributes['company'],
                'sponsors_url' => $attributes['sponsors_url'],
            ];

            if ($avatar instanceof UploadedFile) {
                $path = $this->createImageFiles->handle(
                    [$avatar],
                    directory: 'profile-avatars',
                    attribute: 'avatar',
                )[0]['path'];

                if ($user->avatar_upload_path !== null) {
                    $this->deleteStoredFiles->retain([$user->avatar_upload_path]);
                }

                $userAttributes['avatar_upload_path'] = $path;
            }

            $user->profile->update($profile);
            $user->forceFill($userAttributes)->save();

            event(new UsersUpdated([$user->id], UserUpdate::Account));
            event(new ProfilesUpdated([$user->id]));
        });
    }
}
