<?php

namespace Spatie\UrlSigner\Laravel\Support;

class CanonicalUrl
{
    /**
     * Build a deterministic, canonical representation of a URL.
     *
     * The scheme and host are lowercased, default ports are stripped,
     * percent-encoding is normalized and query parameters are sorted
     * while keeping repeated parameters in their original order.
     *
     * @param  array<string>  $stripParameters
     */
    public static function make(string $url, array $stripParameters = []): string
    {
        $parts = self::parse($url);

        $canonical = '';

        if (array_key_exists('scheme', $parts)) {
            $scheme = strtolower($parts['scheme']);
            $canonical .= $scheme.'://';

            if (isset($parts['user'])) {
                $canonical .= self::normalizeEncoded($parts['user']);

                if (isset($parts['pass'])) {
                    $canonical .= ':'.self::normalizeEncoded($parts['pass']);
                }

                $canonical .= '@';
            }

            $canonical .= strtolower($parts['host'] ?? '');
            $canonical .= self::canonicalPort($parts['port'] ?? null, $scheme);
        }

        $canonical .= self::canonicalPath($parts['path'] ?? '');
        $canonical .= self::canonicalQuery($parts['query'] ?? '', $stripParameters);

        return $canonical;
    }

    /**
     * Parse a URL without mangling repeated or specially named parameters.
     *
     * @return array<string, mixed>
     */
    public static function parse(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false) {
            throw new \InvalidArgumentException("The URL [{$url}] could not be parsed.");
        }

        return $parts;
    }

    /**
     * Split a query string into decoded [name, value] pairs, keeping
     * repeated parameters and their order intact.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function queryPairs(string $query): array
    {
        if ($query === '') {
            return [];
        }

        $pairs = [];

        foreach (explode('&', $query) as $segment) {
            if ($segment === '') {
                continue;
            }

            [$rawName, $rawValue] = array_pad(explode('=', $segment, 2), 2, null);

            if ($rawName === '') {
                continue;
            }

            $pairs[] = [
                self::queryDecode($rawName),
                self::queryDecode($rawValue ?? ''),
            ];
        }

        return $pairs;
    }

    /**
     * Count how many times each parameter name occurs in the query string.
     *
     * @return array<string, int>
     */
    public static function parameterCounts(string $query): array
    {
        $counts = [];

        foreach (self::queryPairs($query) as [$name]) {
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        return $counts;
    }

    private static function canonicalPort(?int $port, string $scheme): string
    {
        if ($port === null) {
            return '';
        }

        $defaultPort = $scheme === 'https' ? 443 : ($scheme === 'http' ? 80 : null);

        if ($port === $defaultPort) {
            return '';
        }

        return ':'.$port;
    }

    private static function canonicalPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        return implode('/', array_map(
            self::normalizeEncoded(...),
            explode('/', $path)
        ));
    }

    /**
     * @param  array<string>  $stripParameters
     */
    private static function canonicalQuery(string $query, array $stripParameters): string
    {
        $pairs = self::queryPairs($query);

        if ($stripParameters !== []) {
            $pairs = array_values(array_filter(
                $pairs,
                fn (array $pair) => ! in_array($pair[0], $stripParameters, true)
            ));
        }

        if ($pairs === []) {
            return '';
        }

        $pairs = self::sortPairs($pairs);

        $segments = array_map(
            fn (array $pair) => self::queryEncode($pair[0]).'='.self::queryEncode($pair[1]),
            $pairs
        );

        return '?'.implode('&', $segments);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $pairs
     * @return array<int, array{0: string, 1: string}>
     */
    private static function sortPairs(array $pairs): array
    {
        $indexed = [];

        foreach ($pairs as $index => $pair) {
            $indexed[] = [$pair, $index];
        }

        usort($indexed, function (array $left, array $right) {
            $comparison = strcmp($left[0][0], $right[0][0]);

            return $comparison !== 0 ? $comparison : $left[1] <=> $right[1];
        });

        return array_map(fn (array $entry) => $entry[0], $indexed);
    }

    private static function queryDecode(string $value): string
    {
        return rawurldecode(str_replace('+', '%20', $value));
    }

    private static function queryEncode(string $value): string
    {
        return str_replace('%20', '+', self::normalizeEncoded($value));
    }

    /**
     * Normalize the percent-encoding of a single URL component:
     * decode everything and re-encode exactly what needs encoding.
     */
    private static function normalizeEncoded(string $value): string
    {
        $decoded = rawurldecode($value);

        return preg_replace_callback(
            '/[^A-Za-z0-9\-._~!$&\'()*+,;=:@\/]+/',
            fn (array $match) => rawurlencode($match[0]),
            $decoded
        );
    }
}
