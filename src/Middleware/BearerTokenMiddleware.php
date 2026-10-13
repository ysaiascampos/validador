<?php

namespace Vendor\Validator\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BearerTokenMiddleware
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
     * Laravel 5+ resolves the middleware through the container, so the
     * config parameter must be optional (it cannot inject a typed
     * Illuminate\Contracts\Config\Repository because that contract does not
     * exist in Laravel 4.2, which passes the repository explicitly).
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

        $apiToken = trim((string) $this->config->get('validator.api_token'));

        if ($apiToken === '') {
            return $this->deny(
                'API token is not configured. Declare the environment variable VALIDATOR_API_TOKEN (in .env / .env.local.php) or set validator.api_token in the config.'
            );
        }

        $providedToken = null;
        $authorization = $this->readAuthorizationHeader($request);

        if ($authorization !== '') {
            if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
                return $this->deny('Invalid Authorization header format. Expected: Bearer <token>');
            }

            $providedToken = trim($matches[1]);
        } else {
            $providedToken = $this->readCustomHeaderToken($request);
        }

        if ($providedToken === null) {
            return $this->deny(
                'Missing API token header. Send "Authorization: Bearer <VALIDATOR_API_TOKEN>" or "X-Validator-Token: <VALIDATOR_API_TOKEN>".'
            );
        }

        if (!hash_equals($apiToken, $providedToken)) {
            return $this->deny(
                'Invalid API token. It must match the value of VALIDATOR_API_TOKEN.'
            );
        }

        return $next($request);
    }

    /**
     * Read the Authorization header, falling back to $_SERVER because
     * Apache/mod_rewrite with PHP CGI discards it before it reaches the app.
     *
     * @param Request $request
     * @return string
     */
    private function readAuthorizationHeader(Request $request)
    {
        $authorization = $request->header('Authorization', '');

        if ($authorization === '') {
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $authorization = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authorization = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }
        }

        return $authorization;
    }

    /**
     * Read the token from the custom X-Validator-Token header. Los headers
     * personalizados sí atraviesan Apache/CGI sin cambios en el servidor.
     *
     * @param Request $request
     * @return string|null
     */
    private function readCustomHeaderToken(Request $request)
    {
        $custom = $request->header('X-Validator-Token', '');

        if ($custom === '' && !empty($_SERVER['HTTP_X_VALIDATOR_TOKEN'])) {
            $custom = $_SERVER['HTTP_X_VALIDATOR_TOKEN'];
        }

        $custom = trim($custom);

        if ($custom === '') {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $custom, $matches)) {
            return trim($matches[1]);
        }

        return $custom;
    }

    /**
     * Determine whether the Bearer token check is enabled.
     *
     * @return bool
     */
    private function authEnabled()
    {
        return (bool) $this->config->get('validator.auth_enabled', true);
    }

    /**
     * Log the denial reason and return the 401 response.
     *
     * @param string $message
     * @return JsonResponse
     */
    private function deny($message)
    {
        $context = array(
            'reason'               => $message,
            'api_token_configured' => !empty($this->config->get('validator.api_token')),
            'http_authorization'   => !empty($_SERVER['HTTP_AUTHORIZATION']),
            'redirect_authorization' => !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']),
            'x_validator_token'    => !empty($_SERVER['HTTP_X_VALIDATOR_TOKEN']),
            'php_auth_user'        => !empty($_SERVER['PHP_AUTH_USER']),
        );

        if (class_exists('Log')) {
            try {
                \Log::warning('[validator] 401: ' . $message, $context);
            } catch (\Exception $e) {
                error_log('[validator] 401: ' . $message . ' ' . json_encode($context));
            }
        } else {
            error_log('[validator] 401: ' . $message . ' ' . json_encode($context));
        }

        return $this->unauthorizedResponse($message);
    }

    /**
     * Generate an unauthorized JSON response.
     *
     * @param string $message
     * @return JsonResponse
     */
    private function unauthorizedResponse($message)
    {
        return new JsonResponse(
            array(
                'status'  => 'error',
                'success' => false,
                'error'   => $message,
            ),
            401
        );
    }
}
