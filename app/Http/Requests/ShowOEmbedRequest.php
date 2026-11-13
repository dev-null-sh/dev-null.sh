<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Queries\CommitEmbedQuery;
use App\Support\EmbedLinks;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

/**
 * oEmbed answers with the statuses its specification gives: 501 for a format it doesn't speak, 404 for a link that isn't
 * one of our commits, and 401 for a commit it won't describe, unsigned or not embeddable.
 */
final class ShowOEmbedRequest extends FormRequest
{
    private ?Commit $commit = null;

    private ?string $card = null;

    public function authorize(): Response
    {
        if ($this->query('format', 'json') !== 'json') {
            return Response::denyWithStatus(501);
        }

        $id = EmbedLinks::commitId($this->link());
        $this->commit = $id === null ? null : Commit::query()->withTrashed()->with('author')->find($id);

        if (! $this->commit instanceof Commit) {
            return Response::denyAsNotFound();
        }

        $this->card = EmbedLinks::signedCardUrl($this->link(), $this->commit);

        return $this->card !== null && CommitEmbedQuery::tombstone($this->commit) === null
            ? Response::allow()
            : Response::denyWithStatus(401);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'maxwidth' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function link(): string
    {
        return (string) $this->query('url');
    }

    public function commit(): Commit
    {
        assert($this->commit instanceof Commit);

        return $this->commit;
    }

    public function card(): string
    {
        assert(is_string($this->card));

        return $this->card;
    }
}
