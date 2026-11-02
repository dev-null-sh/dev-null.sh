<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateDevice;
use App\Actions\DeleteDevice;
use App\Actions\UpdateUserTermsAcceptance;
use App\Exceptions\ApiException;
use App\Http\Requests\CreateApiDeviceRequest;
use App\Http\Requests\DeleteApiDeviceRequest;
use App\Http\Resources\CurrentUserResource;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final readonly class DeviceController
{
    /**
     * The devices that may sign requests as the user, newest approval first, as the web's Devices page lists them.
     */
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        return DeviceResource::collection($user->devices()->latest('approved_at')->get());
    }

    /**
     * Approves the key that signed the request as a device, for the user who approved the app on the web, and answers
     * as GET /api/v1/me does. A code redeemed by another request in the meantime gets the same answer as a wrong one.
     */
    public function store(CreateApiDeviceRequest $request, CreateDevice $action, UpdateUserTermsAcceptance $acceptTerms, NotificationGroupsQuery $notifications): CurrentUserResource|JsonResponse
    {
        $device = $action->handle($request->authorizationCode());

        if (! $device instanceof Device) {
            return ApiException::invalidGrant()->render();
        }

        if ($request->has('terms_version')) {
            $acceptTerms->handle($device->user);
        }

        Auth::setUser($device->user);

        return CurrentUserResource::make($device->user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($device->user),
        ]);
    }

    /**
     * Revokes the device. Revoking the one that signed the request signs the app out on it.
     */
    public function destroy(DeleteApiDeviceRequest $request, Device $device, DeleteDevice $action): Response
    {
        $action->handle($device);

        return response()->noContent();
    }
}
