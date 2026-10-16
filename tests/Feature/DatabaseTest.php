<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('runs on postgres with the search extensions', function (): void {
    $extensions = DB::table('pg_extension')->pluck('extname');

    expect(DB::getDriverName())->toBe('pgsql')
        ->and($extensions)->toContain('vector', 'pg_trgm');
});
