<?php

namespace Spatie\UrlSigner\Laravel\Middleware;

use Closure;
use Spatie\UrlSigner\Laravel\Support\CanonicalUrl;

class ValidateSignature
{
    public function handle($request, Closure $next)
    {
        $urlHasValidSignature = app('url-signer')->validate($this->canonicalRequestUrl($request));

        if (! $urlHasValidSignature) {
            $this->handleUnsignedUrl($request);
        }

        return $next($request);
    }

    /**
     * Reconstruct the requested URL from the raw query string so repeated
     * parameters and their original order are preserved. $request->fullUrl()
     * rebuilds the query from a parsed parameter bag and would lose them.
     */
    protected function canonicalRequestUrl($request): string
    {
        $url = $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo();

        $queryString = $request->server('QUERY_STRING', '');

        if ($queryString !== '' && $queryString !== null) {
            $url .= '?'.$queryString;
        }

        return CanonicalUrl::make($url);
    }

    protected function handleUnsignedUrl($request)
    {
        abort(403);
    }
}
