<?php

declare(strict_types=1);

namespace App\OpenApi;

use App\Models\Commit;
use App\Rules\CommitBodyLength;
use App\Services\Urls;
use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Illuminate\Support\Number;

/**
 * Says how long a commit's body may be as CommitBodyLength measures it, links counting as Urls::MAX_COUNTED_LENGTH
 * characters at most, since the spec otherwise only shows the raw length every body is first held to. Numbers are
 * written in English whatever the app's locale, so the exported spec doesn't depend on who exports it.
 */
final readonly class CommitBodyLengthRule implements RuleTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->instanceOf(CommitBodyLength::class);
    }

    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        return $previous->setDescription(sprintf(
            'At most %s characters, where a link outside code counts as %d at most. Each link must be %s characters at most.',
            Number::format(Commit::MAX_BODY_LENGTH, locale: 'en'),
            Urls::MAX_COUNTED_LENGTH,
            Number::format(Urls::MAX_LENGTH, locale: 'en'),
        ));
    }
}
