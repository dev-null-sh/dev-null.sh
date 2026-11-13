# App/Models guidelines

- Never change a model to return more information to a view or an API. Add the information where the response is built: in the props of the controller, or in a resource in `app/Http/Resources`.
- Never attach a value to a model for a response: no `forceFill()`, no `setAttribute()`, no `setRelation()`, and no assignment such as `$commit->is_own = true`.
- Never add or change a `toArray()` on a model to add, rename or compute a value for a response.
- For example, `$commit->poll->forceFill(['is_own' => $commit->user_id === $viewer->id])` → `'is_own' => $this->user_id === $request->user()?->id` in the resource, or the same key beside the model in the props of the controller.
- Keep loading data with the query: `with()`, `withCount()` and selected columns are not mutations. Keep writing to the database in actions: `forceFill()` followed by `save()` is not a response.
- The existing `toArray()` overrides and `forceFill()` calls on models predate this. Do not add to them or copy them.
