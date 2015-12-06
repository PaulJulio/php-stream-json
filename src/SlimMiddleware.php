<?php
namespace PaulJulio\StreamJSON;

/**
 * Class SlimMiddleware
 * @package PaulJulio\StreamJSON
 *
 * During the request phase, the response body is set to a JSON-based stream
 */
final class SlimMiddleware {
    /**
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next) {
        $next($request, $response->withBody(new StreamJSON()));
        return $response;
    }
}
