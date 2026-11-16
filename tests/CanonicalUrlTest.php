<?php

use Spatie\UrlSigner\Laravel\Support\CanonicalUrl;

it('keeps repeated query parameters and sorts them by name', function () {
    $canonical = CanonicalUrl::make('https://spatie.be/path?b=2&a=1&a=0');

    expect($canonical)->toBe('https://spatie.be/path?a=1&a=0&b=2');
});

it('normalizes the scheme, host and default port', function () {
    expect(CanonicalUrl::make('HTTPS://SPATIE.BE:443/path'))
        ->toBe('https://spatie.be/path')
        ->and(CanonicalUrl::make('http://spatie.be:80/path'))
        ->toBe('http://spatie.be/path')
        ->and(CanonicalUrl::make('http://spatie.be:8080/path'))
        ->toBe('http://spatie.be:8080/path');
});

it('normalizes percent encoding differences', function () {
    expect(CanonicalUrl::make('https://spatie.be/path%7eend?x=%7Ea%20b'))
        ->toBe('https://spatie.be/path~end?x=~a+b');
});

it('treats equivalent encoded query strings as the same url', function () {
    $first = CanonicalUrl::make('https://spatie.be/search?term=hello%20world&filter=%7Eone');
    $second = CanonicalUrl::make('https://spatie.be/search?filter=~one&term=hello+world');

    expect($first)->toBe($second);
});

it('strips the signature parameters when requested', function () {
    $canonical = CanonicalUrl::make(
        'https://spatie.be/path?a=1&expires=123&signature=abc',
        ['expires', 'signature']
    );

    expect($canonical)->toBe('https://spatie.be/path?a=1');
});

it('counts repeated query parameters', function () {
    $counts = CanonicalUrl::parameterCounts('expires=1&expires=2&signature=abc');

    expect($counts)->toBe(['expires' => 2, 'signature' => 1]);
});
