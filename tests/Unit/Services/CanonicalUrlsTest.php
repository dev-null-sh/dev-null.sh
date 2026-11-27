<?php

declare(strict_types=1);

use App\Services\CanonicalUrls;

it('canonicalizes urls, once and for all', function (string $url, string $canonical): void {
    $canonicalUrls = resolve(CanonicalUrls::class);

    expect($canonicalUrls->canonical($url))->toBe($canonical)
        ->and($canonicalUrls->canonical($canonical))->toBe($canonical);
})->with([
    'lowercase scheme and host, not path or query' => ['HTTPS://Example.COM/Docs/Page?Sort=Asc', 'https://example.com/Docs/Page?Sort=Asc'],
    'fragment' => ['https://www.php.net/archive/2026.php#2026-11-18-1', 'https://php.net/archive/2026.php#2026-11-18-1'],
    'code line' => ['https://github.com/laravel/framework/blob/13.x/src/Illuminate/Support/Str.php#L42-L50', 'https://github.com/laravel/framework/blob/13.x/src/Illuminate/Support/Str.php#L42-L50'],
    'app state' => ['https://example.com/#/slides/1', 'https://example.com#/slides/1'],
    'lowercase fragment escapes' => ['https://example.com/docs#caf%c3%a9', 'https://example.com/docs#caf%C3%A9'],
    'text directive' => ['https://example.com/docs#:~:text=highlighted%20quote', 'https://example.com/docs'],
    'fragment with a text directive' => ['https://example.com/docs#section:~:text=quote', 'https://example.com/docs#section'],
    'empty fragment' => ['https://example.com/docs#', 'https://example.com/docs'],
    'default https port' => ['https://example.com:443/docs', 'https://example.com/docs'],
    'default http port' => ['http://example.com:80/docs', 'http://example.com/docs'],
    'other port' => ['https://example.com:8443/docs', 'https://example.com:8443/docs'],
    'lowercase percent-escapes' => ['https://example.com/a%2fb?q=%c3%a9', 'https://example.com/a%2Fb?q=%C3%A9'],
    'root path' => ['https://example.com/', 'https://example.com'],
    'root path with a query' => ['https://example.com/?a=1', 'https://example.com?a=1'],
    'trailing slash of another path' => ['https://example.com/docs/', 'https://example.com/docs/'],
    'www' => ['https://www.example.com/docs', 'https://www.example.com/docs'],
    'parameter order' => ['https://example.com/search?b=2&a=1', 'https://example.com/search?b=2&a=1'],
    'tracking parameters' => ['https://example.com/post?utm_source=x&id=7&utm_campaign=y&fbclid=a&gclid=b&mc_cid=c&mc_eid=d', 'https://example.com/post?id=7'],
    'uppercase tracking parameters' => ['https://example.com/post?UTM_Source=x&Id=7&FBCLID=a', 'https://example.com/post?Id=7'],
    'uppercase site parameters' => ['https://youtu.be/dQw4w9WgXcQ?SI=abc', 'https://youtu.be/dQw4w9WgXcQ'],
    'only tracking parameters' => ['https://example.com/post?utm_source=newsletter', 'https://example.com/post'],
    'empty query' => ['https://example.com/?', 'https://example.com'],
    'http' => ['HTTP://Example.com/?utm_medium=email#top', 'http://example.com#top'],
    'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://youtu.be/dQw4w9WgXcQ'],
    'youtube watch keeps time and playlist' => ['https://m.youtube.com/watch?list=PL123&v=dQw4w9WgXcQ&si=abc&t=43', 'https://youtu.be/dQw4w9WgXcQ?list=PL123&t=43'],
    'youtube watch with a malformed id' => ['https://www.youtube.com/watch?v=short', 'https://youtube.com/watch?v=short'],
    'youtube shorts' => ['https://www.youtube.com/shorts/aqz-KE-bpKQ?feature=share', 'https://youtube.com/shorts/aqz-KE-bpKQ'],
    'youtube channel' => ['https://www.youtube.com/@nunomaduro?pp=x&is=y', 'https://youtube.com/@nunomaduro'],
    'youtube short link' => ['https://youtu.be/dQw4w9WgXcQ?si=abc&t=43', 'https://youtu.be/dQw4w9WgXcQ?t=43'],
    'youtube music' => ['https://music.youtube.com/watch?v=dQw4w9WgXcQ&si=abc', 'https://music.youtube.com/watch?v=dQw4w9WgXcQ&si=abc'],
    'twitter' => ['https://twitter.com/taylorotwell/status/1?s=20&t=abc', 'https://x.com/taylorotwell/status/1'],
    'mobile twitter' => ['https://mobile.twitter.com/taylorotwell', 'https://x.com/taylorotwell'],
    'www x' => ['https://www.x.com/taylorotwell?ref_src=twsrc&ref=home', 'https://x.com/taylorotwell'],
    'mobile x' => ['https://mobile.x.com/taylorotwell?lang=en', 'https://x.com/taylorotwell?lang=en'],
    'instagram' => ['https://www.instagram.com/p/abc/?igsh=1&igshid=2&srtk=3&exln=4', 'https://instagram.com/p/abc/'],
    'linkedin' => ['https://www.linkedin.com/in/someone', 'https://linkedin.com/in/someone'],
    'linkedin country' => ['https://de.linkedin.com/in/someone', 'https://linkedin.com/in/someone'],
    'reddit' => ['https://old.reddit.com/r/laravel', 'https://reddit.com/r/laravel'],
    'new reddit' => ['https://new.reddit.com/r/php', 'https://reddit.com/r/php'],
    'product hunt' => ['https://www.producthunt.com/posts/x', 'https://producthunt.com/posts/x'],
    'php' => ['https://www.php.net/releases/8.5/', 'https://php.net/releases/8.5/'],
    'twitch' => ['https://www.twitch.tv/someone', 'https://twitch.tv/someone'],
    'meetup' => ['https://www.meetup.com/laravel', 'https://meetup.com/laravel'],
    'spotify' => ['https://open.spotify.com/episode/abc?si=123', 'https://open.spotify.com/episode/abc'],
    'google drive' => ['https://drive.google.com/file/d/abc/view?usp=sharing', 'https://drive.google.com/file/d/abc/view'],
    'google docs' => ['https://docs.google.com/document/d/abc/edit?usp=drivesdk&tab=t.0', 'https://docs.google.com/document/d/abc/edit?tab=t.0'],
    'alibaba cloud' => ['https://www.alibabacloud.com/blog/x?spm=a2c65', 'https://www.alibabacloud.com/blog/x'],
    'github' => ['https://github.com/laravel/framework/?tab=readme&s=1', 'https://github.com/laravel/framework/?tab=readme&s=1'],
]);

it('returns what it can not parse as is', function (string $url): void {
    expect(resolve(CanonicalUrls::class)->canonical($url))->toBe($url);
})->with([
    'not a url' => 'not a url',
    'other scheme' => 'ftp://Example.com/file#x',
]);
