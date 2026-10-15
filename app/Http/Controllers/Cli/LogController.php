<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cli;

use App\Enums\Log;
use App\Models\User;
use App\Services\Terminal\TerminalRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class LogController
{
    public function __construct(private TerminalRenderer $renderer)
    {
        //
    }

    /**
     * Handles /log for terminal and browser clients.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        if (auth()->check()) {
            $user = auth()->user();
            assert($user instanceof User);

            if (TerminalRenderer::wantsCli($request)) {
                $limit = min(max($request->integer('limit', 20), 1), 50);
                $commits = Log::Following->query()->builder($user)->take($limit)->get();
                $color = TerminalRenderer::isColorSupported($request);
                $force16 = TerminalRenderer::is16Color($request);
                $content = $this->renderer->feed($commits, $color, $force16, title: 'Log: following');

                return response($content)->header('Content-Type', 'text/plain; charset=utf-8');
            }

            return to_route('log.show', ['log' => Log::Following]);
        }

        if (TerminalRenderer::wantsCli($request)) {
            $color = TerminalRenderer::isColorSupported($request);

            return response($this->renderer->landing($color))->header('Content-Type', 'text/plain; charset=utf-8');
        }

        return to_route('home');
    }
}
