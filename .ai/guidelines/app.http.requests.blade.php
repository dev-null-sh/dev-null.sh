# App/Http/Requests guidelines

- Never use policies or gates: no `app/Policies`, no `Gate::define()`, no `can:` middleware, no `$user->can()`, no `$this->authorize()`.
- Every authorization, validation, or business rule check for an HTTP endpoint lives in a form request, in its `authorize()` or `rules()` method. This includes `index` and `show` endpoints.
- Controllers and actions never check anything themselves: no `abort()`, `abort_if()`, `abort_unless()`, `throw_if()`, `throw_unless()`, `Validator::make()`, `$request->validate()`, or `ValidationException`.
- Create a dedicated form request for each endpoint that checks anything, named after what it does: `ShowCommitRequest`, `CreateBookmarkRequest`, `DeleteCommitRequest`.
- When hiding something should look like it does not exist, return `Response::denyAsNotFound()` from `authorize()` instead of `false`.
- Put input interpretation in form request methods (`$request->tab()`, `$request->parent()`), and complex checks in `App\Rules` validation rules.
- `tests/Unit/ArchTest.php` enforces this.

@boostsnippet('Example form request', 'php')
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateBookmarkRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->isVisibleTo($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
@endboostsnippet
