<?php

use Spatie\UrlSigner\Laravel\Facades\UrlSigner;

it('validates a url that was signed with differently ordered parameters', function () {
    $signedUrl = UrlSigner::sign('https://spatie.be/protected?b=2&a=1', now()->addHour());

    $parts = explode('&', substr($signedUrl, strpos($signedUrl, '?') + 1));
    shuffle($parts);
    $shuffledUrl = substr($signedUrl, 0, strpos($signedUrl, '?') + 1).implode('&', $parts);

    expect(UrlSigner::validate($shuffledUrl))->toBeTrue();
});

it('keeps repeated query parameters part of the signature', function () {
    $signedUrl = UrlSigner::sign('https://spatie.be/protected?tag=a&tag=b', now()->addHour());

    expect(UrlSigner::validate($signedUrl))->toBeTrue();

    $tamperedUrl = str_replace('tag=a&tag=b', 'tag=b&tag=a', $signedUrl);

    expect(UrlSigner::validate($tamperedUrl))->toBeFalse();

    $duplicatedUrl = str_replace('tag=a&tag=b', 'tag=a&tag=b&tag=c', $signedUrl);

    expect(UrlSigner::validate($duplicatedUrl))->toBeFalse();
});

it('normalizes percent encoding between signing and validation', function () {
    $signedUrl = UrlSigner::sign('https://spatie.be/protected?term=hello%20world', now()->addHour());

    $equivalentUrl = str_replace('term=hello+world', 'term=hello%20world',
        str_replace('term=hello%20world', 'term=hello+world', $signedUrl)
    );

    expect(UrlSigner::validate($equivalentUrl))->toBeTrue();

    $differentValue = str_replace('hello', 'h%C3%A9llo', $signedUrl);

    expect(UrlSigner::validate($differentValue))->toBeFalse();
});

it('normalizes the scheme, host and default port between signing and validation', function () {
    $signedUrl = UrlSigner::sign('https://spatie.be/protected', now()->addHour());

    $withDefaultPort = str_replace('https://spatie.be', 'https://spatie.be:443', $signedUrl);

    expect(UrlSigner::validate($withDefaultPort))->toBeTrue();

    $withDifferentPort = str_replace('https://spatie.be', 'https://spatie.be:8443', $signedUrl);

    expect(UrlSigner::validate($withDifferentPort))->toBeFalse();
});

it('rejects a url with a duplicated expires parameter', function () {
    $signedUrl = UrlSigner::sign('https://spatie.be/protected', now()->addHour());

    $forgedUrl = $signedUrl.'&expires='.now()->addHour()->timestamp;

    expect(UrlSigner::validate($forgedUrl))->toBeFalse();
});

it('rejects a url with a duplicated signature parameter', function () {
    $signedUrl = UrlSigner::sign('https://spatie.be/protected', now()->addHour());

    $forgedUrl = $signedUrl.'&signature=forged';

    expect(UrlSigner::validate($forgedUrl))->toBeFalse();
});

it('refuses to sign a url with a duplicated expires or signature parameter', function () {
    UrlSigner::sign('https://spatie.be/protected?expires=1&expires=2', now()->addHour());
})->throws(InvalidArgumentException::class);

it('rejects a signature that is only valid because of parameter parsing differences', function () {
    $signedUrl = UrlSigner::sign('https://spatie.be/protected?name=value', now()->addHour());

    $bracketVariant = str_replace('name=value', 'name[]=value', $signedUrl);

    expect(UrlSigner::validate($bracketVariant))->toBeFalse();
});

it('still validates right up to the expiration boundary', function () {
    $signedUrl = UrlSigner::sign('https://spatie.be', now()->addSeconds(1));

    expect(UrlSigner::validate($signedUrl))->toBeTrue();

    sleep(2);

    expect(UrlSigner::validate($signedUrl))->toBeFalse();
});

it('keeps the sign and validate method signatures working as before', function () {
    $withIntegerExpiration = UrlSigner::sign('https://spatie.be', 30);
    expect(UrlSigner::validate($withIntegerExpiration))->toBeTrue();

    $withDateTimeExpiration = UrlSigner::sign('https://spatie.be', new DateTime('+30 seconds'));
    expect(UrlSigner::validate($withDateTimeExpiration))->toBeTrue();

    $withCustomKey = UrlSigner::sign('https://spatie.be', signatureKey: 'my-other-secret');
    expect(UrlSigner::validate($withCustomKey, signatureKey: 'my-other-secret'))->toBeTrue();
    expect(UrlSigner::validate($withCustomKey, signatureKey: 'wrong-secret'))->toBeFalse();
});
