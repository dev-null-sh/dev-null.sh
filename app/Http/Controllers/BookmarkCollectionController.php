<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateBookmarkCollection;
use App\Actions\DeleteBookmarkCollection;
use App\Actions\UpdateBookmarkCollection;
use App\Http\Requests\CreateBookmarkCollectionRequest;
use App\Http\Requests\DeleteBookmarkCollectionRequest;
use App\Http\Requests\ShowBookmarkCollectionRequest;
use App\Http\Requests\UpdateBookmarkCollectionRequest;
use App\Models\BookmarkCollection;
use App\Models\User;
use App\Queries\BookmarkCollectionsQuery;
use App\Queries\SavedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BookmarkCollectionController
{
    public function show(ShowBookmarkCollectionRequest $request, #[CurrentUser] User $user, BookmarkCollection $bookmarkCollection, SavedCommitsQuery $saved, BookmarkCollectionsQuery $collections): Response
    {
        return Inertia::render('bookmark/index', [
            'collection' => $bookmarkCollection->only('id', 'name'),
            'collections' => fn (): Collection => $collections->get($user),
            'commits' => Inertia::scroll(fn () => $saved->builder($user, $bookmarkCollection)->cursorPaginate(20)),
        ]);
    }

    public function store(CreateBookmarkCollectionRequest $request, #[CurrentUser] User $user, CreateBookmarkCollection $action): RedirectResponse
    {
        $collection = $action->handle($user, $request->name());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Collection created.')]);

        return to_route('bookmark-collection.show', $collection);
    }

    public function update(UpdateBookmarkCollectionRequest $request, BookmarkCollection $bookmarkCollection, UpdateBookmarkCollection $action): RedirectResponse
    {
        $action->handle($bookmarkCollection, $request->name());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Collection renamed.')]);

        return back();
    }

    public function destroy(DeleteBookmarkCollectionRequest $request, BookmarkCollection $bookmarkCollection, DeleteBookmarkCollection $action): RedirectResponse
    {
        $action->handle($bookmarkCollection);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Collection deleted. Its commits are still saved.')]);

        return to_route('bookmark.index');
    }
}
