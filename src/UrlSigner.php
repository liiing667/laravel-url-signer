<?php

namespace Spatie\UrlSigner\Laravel;

use DateTime;
use Spatie\UrlSigner\Md5UrlSigner;
use Spatie\UrlSigner\Laravel\Support\CanonicalUrl;

class UrlSigner extends Md5UrlSigner
{
    public function sign(
        string $url,
        int|DateTime|null $expiration = null,
        ?string $signatureKey = null,
    ): string {
        $expiration ??= config('url-signer.default_expiration_time_in_seconds');

        $signatureKey ??= $this->defaultSignatureKey;

        $this->guardAgainstDuplicatedSignatureParameters($url);

        $expirationTimestamp = $this->getExpirationTimestamp($expiration);

        $canonicalUrl = $this->canonicalIntendedUrl($url);

        $signature = $this->createSignature($canonicalUrl, $expirationTimestamp, $signatureKey);

        return $this->appendSignatureParameters($canonicalUrl, [
            $this->expiresParameterName => $expirationTimestamp,
            $this->signatureParameterName => $signature,
        ]);
    }

    public function validate(string $url, ?string $signatureKey = null): bool
    {
        $signatureKey ??= $this->defaultSignatureKey;

        try {
            $query = CanonicalUrl::parse($url)['query'] ?? '';
        } catch (\InvalidArgumentException $exception) {
            return false;
        }

        if ($this->hasDuplicatedSignatureParameters($query)) {
            return false;
        }

        $queryParameters = [];

        foreach (CanonicalUrl::queryPairs($query) as [$name, $value]) {
            $queryParameters[$name] = $value;
        }

        if (! array_key_exists($this->expiresParameterName, $queryParameters)
            || ! array_key_exists($this->signatureParameterName, $queryParameters)) {
            return false;
        }

        $expiration = $queryParameters[$this->expiresParameterName];
        $providedSignature = $queryParameters[$this->signatureParameterName];

        $timestamp = filter_var($expiration, FILTER_VALIDATE_INT);

        if ($timestamp === false || ! $this->isFuture($timestamp)) {
            return false;
        }

        $canonicalUrl = $this->canonicalIntendedUrl($url);

        $validSignature = $this->createSignature($canonicalUrl, (string) $timestamp, $signatureKey);

        return hash_equals($validSignature, $providedSignature);
    }

    protected function canonicalIntendedUrl(string $url): string
    {
        return CanonicalUrl::make($url, [
            $this->expiresParameterName,
            $this->signatureParameterName,
        ]);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    protected function appendSignatureParameters(string $url, array $parameters): string
    {
        $parts = CanonicalUrl::parse($url);

        $segments = [];

        if (isset($parts['query']) && $parts['query'] !== '') {
            $segments[] = $parts['query'];
        }

        foreach ($parameters as $name => $value) {
            $segments[] = rawurlencode($name).'='.rawurlencode($value);
        }

        $base = isset($parts['query']) ? substr($url, 0, strpos($url, '?')) : $url;

        return $base.'?'.implode('&', $segments);
    }

    protected function guardAgainstDuplicatedSignatureParameters(string $url): void
    {
        $query = CanonicalUrl::parse($url)['query'] ?? '';

        if ($this->hasDuplicatedSignatureParameters($query)) {
            throw new \InvalidArgumentException(
                'A URL cannot be signed when it contains a duplicated "'.$this->expiresParameterName
                .'" or "'.$this->signatureParameterName.'" query parameter.'
            );
        }
    }

    protected function hasDuplicatedSignatureParameters(string $query): bool
    {
        $parameterCounts = CanonicalUrl::parameterCounts($query);

        return ($parameterCounts[$this->expiresParameterName] ?? 0) > 1
            || ($parameterCounts[$this->signatureParameterName] ?? 0) > 1;
    }
}
