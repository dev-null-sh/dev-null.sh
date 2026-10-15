<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Agent Output Paths
    |--------------------------------------------------------------------------
    |
    | Boost writes its guidelines and skills into the agnostic-ai sources.
    | `agnostic-ai sync` then generates the files for every AI tool. The
    | MCP server lives in .agnostic-ai/mcps/, so Boost's copy stays in .ai/.
    |
    */

    'agents' => [
        'claude_code' => [
            'guidelines_path' => '.agnostic-ai/AGNOSTIC_AI.md',
            'skills_path' => '.agnostic-ai/skills',
            'mcp_config_path' => '.ai/mcp/mcp.json',
        ],
    ],

];
