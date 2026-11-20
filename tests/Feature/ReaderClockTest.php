<?php

declare(strict_types=1);

use App\Models\User;

it('reads the clock the browser wrote in plain cookies', function (): void {
    $this->actingAs(User::factory()->create())
        ->withUnencryptedCookie('time_zone', 'Pacific/Auckland')
        ->withUnencryptedCookie('hour_cycle', 'h23')
        ->withUnencryptedCookie('date_order', 'day_first')
        ->get(route('log.show', 'latest'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('clock', ['timeZone' => 'Pacific/Auckland', 'hourCycle' => 'h23', 'dateOrder' => 'day_first']));
});
