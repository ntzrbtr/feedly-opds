<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Schützt die OPDS-Endpunkte mittels statischem Token aus `config('opds.auth_token')`.
 *
 * Unterstützt werden zwei Transport-Varianten, die von OPDS-Reader-Apps gleichermaßen
 * genutzt werden:
 *
 *  1. `Authorization: Basic <base64(benutzername:token)>`-Header
 *     (der Benutzername wird ignoriert, das Passwort muss das Token sein)
 *  2. `?token=<token>`-Query-Parameter
 *
 * Fehlt das Token oder weicht es ab, wird mit 401 Unauthorized geantwortet.
 */
final class EnsureOpdsToken
{
    public function handle(Request $request, \Closure $next): Response
    {
        $expected = (string) config('opds.auth_token');

        if ($expected === '' || ! hash_equals($expected, (string) $this->extractToken($request))) {
            throw new UnauthorizedHttpException('Basic realm="OPDS"', 'Invalid OPDS token.');
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $queryToken = $request->query('token');
        if (is_string($queryToken) && $queryToken !== '') {
            return $queryToken;
        }

        $header = $request->headers->get('Authorization');
        if (is_string($header) && str_starts_with(strtolower($header), 'basic ')) {
            $decoded = base64_decode(substr($header, 6), strict: true);
            if ($decoded === false) {
                return null;
            }

            $parts = explode(':', $decoded, 2);

            // Benutzername wird ignoriert; das Passwort ist das OPDS-Token.
            return $parts[1] ?? $parts[0];
        }

        return null;
    }
}
