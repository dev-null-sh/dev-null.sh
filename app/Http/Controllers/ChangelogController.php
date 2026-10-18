<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\ChangelogQuery;
use Illuminate\Http\JsonResponse;

final readonly class ChangelogController
{
    public function index(ChangelogQuery $changelog): JsonResponse
    {
        return response()->json([
            'pullRequests' => $changelog->get(),
            'commitsUrl' => 'https://github.com/'.ChangelogQuery::Repository.'/commits/'.ChangelogQuery::Branch,
        ]);
    }
}
