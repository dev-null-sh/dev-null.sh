<?php

declare(strict_types=1);

use App\Actions\CreateImageFiles;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

arch()->preset()->php();
arch()->preset()->strict();
arch()->preset()->laravel();
arch()->preset()->security()->ignoring([
    'assert',
]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

arch('no policies or gates')
    ->expect('App')
    ->not->toHaveSuffix('Policy')
    ->not->toUse([
        Gate::class,
        Illuminate\Contracts\Auth\Access\Gate::class,
        HandlesAuthorization::class,
        AuthorizesRequests::class,
    ]);

arch('controllers leave authorization and validation to form requests')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'abort',
        'abort_if',
        'abort_unless',
        'throw_if',
        'throw_unless',
        'validator',
        Validator::class,
        ValidationException::class,
    ]);

// Images can only fail to decode once they are processed...
arch('actions leave authorization and validation to form requests')
    ->expect('App\Actions')
    ->not->toUse([
        'abort',
        'abort_if',
        'abort_unless',
        'throw_if',
        'throw_unless',
        'validator',
        Validator::class,
        ValidationException::class,
    ])
    ->ignoring([
        CreateImageFiles::class,
    ]);

test('actions only create, update, or delete', function (): void {
    foreach (File::allFiles(app_path('Actions')) as $file) {
        expect($file->getFilenameWithoutExtension())->toMatch('/^(Create|Update|Delete)[A-Z]/');
    }
});

test('controllers are cruddy by design, or invokable', function (): void {
    foreach (File::allFiles(app_path('Http/Controllers')) as $file) {
        $controller = 'App\\Http\\Controllers\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        $methods = array_values(array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            array_filter(
                new ReflectionClass($controller)->getMethods(ReflectionMethod::IS_PUBLIC),
                fn (ReflectionMethod $method): bool => ! $method->isConstructor(),
            ),
        ));

        expect($methods === ['__invoke'] || array_diff($methods, ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']) === [])
            ->toBeTrue("{$controller} may only have the resource methods, or a single __invoke method.");
    }
});

test('migrations only go up', function (): void {
    foreach (File::files(database_path('migrations')) as $file) {
        expect(method_exists(require $file->getPathname(), 'down'))->toBeFalse("{$file->getFilename()} may not have a down method.");
    }
});

test('form requests are named after the controller method they serve', function (): void {
    foreach (File::allFiles(app_path('Http/Requests')) as $file) {
        expect($file->getFilenameWithoutExtension())->toMatch('/^(List|Show|Create|Update|Delete)[A-Z]\w*Request$/');
    }
});
