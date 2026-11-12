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
        ])->withViewData(['preview' => $metadata->page(
            'Partners - /dev/null',
            'The people and companies who sponsor /dev/null on GitHub, and where their money goes: hosting, AI, and getting the team together to build more.',
            route('partner.index'),
        )]);
    }
}
