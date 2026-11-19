<?php

namespace Vendor\Validator\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportBasicAuthMiddleware
{
    /**
     * The config repository instance.
     *
     * @var \Illuminate\Contracts\Config\Repository|\Illuminate\Config\Repository
     */
    private $config;

    /**
     * Constructor.
     *
     * Igual que BearerTokenMiddleware: en Laravel 5+ el contenedor resuelve el
     * middleware, en 4.2 la aplicacion pasa el repositorio explicitamente.
     *
     * @param \Illuminate\Contracts\Config\Repository|\Illuminate\Config\Repository|null $config
     */
    public function __construct($config = null)
    {
        $this->config = $config ?: app('config');
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$this->authEnabled()) {
            return $next($request);
        }

        $user = trim((string) $this->option('user'));
        $password = (string) $this->option('password');

        // Fail-closed: sin credenciales configuradas el reporte no se muestra.
        if ($user === '' || $password === '') {
            return $this->deny(
                'El reporte no tiene credenciales configuradas. Declara VALIDATOR_REPORT_USER y VALIDATOR_REPORT_PASSWORD.'
            );
        }

        $credentials = $this->credentials($request);

        if ($credentials === null) {
            return $this->challenge();
        }

        list($providedUser, $providedPassword) = $credentials;

        $userMatches = hash_equals($user, $providedUser);
        $passwordMatches = hash_equals($password, $providedPassword);

        if (!$userMatches || !$passwordMatches) {
            return $this->challenge();
        }

        return $next($request);
    }

    /**
     * Read the Basic Auth credentials from the request.
     *
     * Apache con PHP CGI descarta el header Authorization, por eso se revisan
     * primero las variables PHP_AUTH_* y luego el header.
     *
     * @param Request $request
     * @return array|null array($user, $password)
     */
    private function credentials(Request $request)
    {
        $user = $this->serverValue($request, 'PHP_AUTH_USER');
        $password = $this->serverValue($request, 'PHP_AUTH_PW');

        if ($user === null || $user === '') {
            $header = $this->authorizationHeader($request);

            if ($header !== '' && preg_match('/^Basic\s+(.+)$/i', $header, $matches)) {
                $decoded = base64_decode(trim($matches[1]), true);

                if ($decoded !== false && strpos($decoded, ':') !== false) {
                    list($user, $password) = explode(':', $decoded, 2);
                }
            }
        }

        if ($user === null || $user === '') {
            return null;
        }

        return array((string) $user, (string) $password);
    }

    /**
     * @param Request $request
     * @param string $key
     * @return string|null
     */
    private function serverValue(Request $request, $key)
    {
        $value = method_exists($request, 'server') ? $request->server($key, null) : null;

        if ($value === null && isset($_SERVER[$key])) {
            $value = $_SERVER[$key];
        }

        return $value === null ? null : (string) $value;
    }

    /**
     * Read the Authorization header, falling back to $_SERVER.
     *
     * @param Request $request
     * @return string
     */
    private function authorizationHeader(Request $request)
    {
        $authorization = (string) $request->header('Authorization', '');

        if ($authorization === '') {
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $authorization = (string) $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authorization = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }
        }

        return $authorization;
    }

    /**
     * Whether the report protection is enabled.
     *
     * @return bool
     */
    private function authEnabled()
    {
        $enabled = $this->option('enabled');

        return $enabled === null ? true : (bool) $enabled;
    }

    /**
     * Read an option from validator.report.auth.
     *
     * @param string $key
     * @return mixed
     */
    private function option($key)
    {
        return $this->config->get('validator.report.auth.' . $key);
    }

    /**
     * Ask the browser for credentials.
     *
     * @return Response
     */
    private function challenge()
    {
        return $this->unauthorized('Autenticacion requerida para ver el reporte.', true);
    }

    /**
     * Deny the request without asking for credentials.
     *
     * @param string $message
     * @return Response
     */
    private function deny($message)
    {
        return $this->unauthorized($message, false);
    }

    /**
     * Build the 401 response.
     *
     * @param string $message
     * @param bool $withChallenge
     * @return Response
     */
    private function unauthorized($message, $withChallenge)
    {
        $headers = array(
            'Content-Type'     => 'text/plain; charset=UTF-8',
            'Cache-Control'    => 'no-store',
            'Referrer-Policy'  => 'no-referrer',
            'X-Robots-Tag'     => 'noindex',
        );

        if ($withChallenge) {
            $realm = trim((string) $this->option('realm'));
            $realm = $realm === '' ? 'Validador RSH' : $realm;
            $headers['WWW-Authenticate'] = 'Basic realm="' . str_replace('"', '', $realm) . '"';
        }

        return new Response($message, 401, $headers);
    }
}
