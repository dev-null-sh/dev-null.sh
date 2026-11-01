<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\CreateUserBadge;
use App\Enums\ActivityGraphTheme;
use App\Models\User;
use DOMDocument;
use DOMXPath;

function userBadgeXpath(string $svg): DOMXPath
{
    $document = new DOMDocument;

    expect($document->loadXML($svg))->toBeTrue();

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');

    return $xpath;
}

it('generates a valid XML SVG follow badge with branding and handle', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);

    $action = resolve(CreateUserBadge::class);
    $svg = $action->handle($user, ActivityGraphTheme::Light);

    $xpath = userBadgeXpath($svg);

    $nodes = $xpath->query('//svg:text');
    expect($nodes)->not->toBeFalse()
        ->and($nodes->length)->toBeGreaterThan(0);

    $text = $xpath->evaluate('normalize-space(/svg:svg)');
    assert(is_string($text));
    expect($text)->toContain('/dev/null')
        ->toContain('follow @octocat');
});

it('formats follow message with user handle', function (): void {
    $user = User::factory()->create(['handle' => 'alice']);

    $action = resolve(CreateUserBadge::class);
    expect($action->message($user))->toBe('follow @alice');
});

it('calculates text width based on length', function (): void {
    $action = resolve(CreateUserBadge::class);

    expect($action->textWidth('a'))->toBeLessThan($action->textWidth('abc'))
        ->and($action->textWidth('follow @octocat'))->toBeGreaterThan(0);
});
