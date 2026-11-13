<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\PartnersQuery;
use App\Services\PageMetadata;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PartnerController
{
    public function index(PartnersQuery $partners, PageMetadata $metadata): Response
    {
        return Inertia::render('partner/index', [
            'tiers' => $partners->get(...),
        ])->withViewData(['preview' => $metadata->partners()]);
    }
}
