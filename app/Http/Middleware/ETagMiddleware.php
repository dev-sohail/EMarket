<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ETagMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return $response;
        }

        $etag = '"'.md5($content).'"';
        $response->headers->set('ETag', $etag);

        if ($request->headers->get('If-None-Match') === $etag) {
            $response->setStatusCode(304);
            $response->setContent('');
        }

        return $response;
    }
}
