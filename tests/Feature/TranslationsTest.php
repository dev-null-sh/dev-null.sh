<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;

/**
 * @return array<string, mixed>
 */
function translationGroup(string $locale, string $group): array
{
    $path = lang_path("{$locale}/{$group}.php");

    expect($path)->toBeFile();

    /** @var array<string, mixed> $translations */
    $translations = require $path;

    return $translations;
}

/**
 * @return list<string>
 */
function translationGroups(): array
{
    return array_map(
        fn (SplFileInfo $file): string => $file->getBasename('.php'),
        File::files(lang_path('en')),
    );
}

/**
 * @return list<string>
 */
function placeholders(string $line): array
{
    preg_match_all('/:([a-z_]+)/', $line, $matches);

    $names = array_values(array_unique($matches[1]));
    sort($names);

    return $names;
}

it('translates every english line into every language', function (Locale $locale): void {
    foreach (translationGroups() as $group) {
        $english = Arr::dot(translationGroup('en', $group));
        $translated = Arr::dot(translationGroup($locale->value, $group));

        expect(array_keys($translated))->toEqualCanonicalizing(array_keys($english), "{$locale->value}/{$group}.php has different keys than en/{$group}.php");

        foreach ($english as $key => $line) {
            expect($translated[$key])->toBeString()->not->toBe('', "{$locale->value}/{$group}.php is missing {$key}")
                ->and(placeholders($translated[$key]))->toBe(placeholders((string) $line), "{$locale->value}/{$group}.php changes the placeholders of {$key}");
        }
    }
})->with(Locale::cases());

it('exports every language file to the json the frontend reads', function (Locale $locale): void {
    foreach (translationGroups() as $group) {
        $json = resource_path("js/lang/{$locale->value}/{$group}.json");

        expect($json)->toBeFile("Run `php artisan erag:generate-lang` to export {$locale->value}/{$group}.php")
            ->and(json_decode((string) file_get_contents($json), true))->toBe(translationGroup($locale->value, $group));
    }
})->with(Locale::cases());

it('shows pages in the language from the locale cookie', function (): void {
    $this->actingAs(User::factory()->create())
        ->withUnencryptedCookie('locale', 'hi')
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('locale', 'hi')
            ->where('lang.settings.language.title', translationGroup('hi', 'settings')['language']['title']));
});

it('shows pages in english by default', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('locale', 'en')
            ->where('lang.settings.language.title', 'Language'));
});
