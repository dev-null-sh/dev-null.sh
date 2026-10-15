{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>{{ __('Following @:username on /dev/null', ['username' => $user->username]) }}</title>
        <link>{{ route('log.show', \App\Enums\Log::Following) }}</link>
        <atom:link href="{{ route('following-feed.show', $user->feed_token) }}" rel="self" type="application/rss+xml" />
        <description>{{ __('The latest commits from the people @:username follows on /dev/null.', ['username' => $user->username]) }}</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        @if ($commits->isNotEmpty())
        <lastBuildDate>{{ $commits->first()->created_at->toRssString() }}</lastBuildDate>
        @endif
        @foreach ($commits as $commit)
        <item>
            <title>{{ '@'.$commit->user->username }}: {{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($commit->body), 80, '…', preserveWords: true) }}</title>
            <link>{{ route('commit.show', [$commit->user, $commit]) }}</link>
            <guid isPermaLink="true">{{ route('commit.show', [$commit->user, $commit]) }}</guid>
            <dc:creator>{{ $commit->user->nameWithHandle() }}</dc:creator>
            <pubDate>{{ $commit->created_at->toRssString() }}</pubDate>
            <description>{{ $commit->body }}</description>
        </item>
        @endforeach
    </channel>
</rss>
