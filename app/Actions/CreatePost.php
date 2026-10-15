<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreatePost
{
    /**
     * A hashtag needs at least one letter (so "#1" is not a topic), is at most 50 characters,
     * and must not be glued to a preceding word (so "C#" or "a#b" are not topics).
     * Mirrored in resources/js/components/post-body.tsx.
     */
    private const string HASHTAG = '/(?<![\p{L}\p{N}_#])#(?=[\p{N}_]*\p{L})([\p{L}\p{N}_]{1,50})(?![\p{L}\p{N}_])/u';

    public function handle(User $user, string $body): Post
    {
        return DB::transaction(function () use ($user, $body): Post {
            $post = $user->posts()->create([
                'body' => $body,
            ]);

            $topicIds = array_map(
                fn (string $name): string => Topic::query()->firstOrCreate(['name' => $name])->id,
                $this->hashtags($body),
            );

            $post->topics()->attach($topicIds);

            return $post;
        });
    }

    /**
     * @return list<string>
     */
    private function hashtags(string $body): array
    {
        preg_match_all(self::HASHTAG, $body, $matches);

        return array_values(array_unique(array_map(mb_strtolower(...), $matches[1])));
    }
}
