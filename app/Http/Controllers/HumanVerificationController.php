<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateHumanVerificationRequest;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class HumanVerificationController
{
    public function show(): Response
    {
        return Inertia::render('human-verification/show');
    }

    public function store(CreateHumanVerificationRequest $request): SymfonyResponse
    {
        $request->session()->put(CreateHumanVerificationRequest::SessionKey, true);

        return Inertia::location(route('login'));
    }
}
