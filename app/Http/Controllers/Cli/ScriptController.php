<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cli;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final readonly class ScriptController
{
    /**
     * The `devnull commit` script: curl -fsSL https://dev-null.sh/commit | bash -s -- 'Hello world!'
     */
    public function show(): Response
    {
        $script = Str::replace('__DEVNULL_URL__', url('/'), File::get(resource_path('scripts/commit.sh')));

        return response($script)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
