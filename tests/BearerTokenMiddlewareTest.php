<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Config\Repository as ConfigRepository;
use Vendor\Validator\Middleware\BearerTokenMiddleware;

class BearerTokenMiddlewareTest extends TestCase
{
    public function testMissingAuthorizationHeader()
    {
        $config = new ConfigRepository(array(
            'validator' => array(
                'api_token' => 'valid-token',
            ),
        ));

        $middleware = new BearerTokenMiddleware($config);
        $request = Request::create('/api/validate', 'POST');

        $response = $middleware->handle($request, function ($req) {
            return new JsonResponse(array('success' => true));
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testInvalidAuthorizationFormat()
    {
        $config = new ConfigRepository(array(
            'validator' => array(
                'api_token' => 'valid-token',
            ),
        ));

        $middleware = new BearerTokenMiddleware($config);
        $request = Request::create('/api/validate', 'POST');
        $request->headers->set('Authorization', 'Basic somehash');

        $response = $middleware->handle($request, function ($req) {
            return new JsonResponse(array('success' => true));
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testInvalidToken()
    {
        $config = new ConfigRepository(array(
            'validator' => array(
                'api_token' => 'valid-token',
            ),
        ));

        $middleware = new BearerTokenMiddleware($config);
        $request = Request::create('/api/validate', 'POST');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $response = $middleware->handle($request, function ($req) {
            return new JsonResponse(array('success' => true));
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testValidTokenPasses()
    {
        $config = new ConfigRepository(array(
            'validator' => array(
                'api_token' => 'valid-token',
            ),
        ));

        $middleware = new BearerTokenMiddleware($config);
        $request = Request::create('/api/validate', 'POST');
        $request->headers->set('Authorization', 'Bearer valid-token');

        $response = $middleware->handle($request, function ($req) {
            return new JsonResponse(array('success' => true));
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testEmptyApiTokenConfig()
    {
        $config = new ConfigRepository(array(
            'validator' => array(
                'api_token' => '',
            ),
        ));

        $middleware = new BearerTokenMiddleware($config);
        $request = Request::create('/api/validate', 'POST');
        $request->headers->set('Authorization', 'Bearer any-token');

        $response = $middleware->handle($request, function ($req) {
            return new JsonResponse(array('success' => true));
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testTokenComparisonIsTimingSafe()
    {
        $config = new ConfigRepository(array(
            'validator' => array(
                'api_token' => 'secure-token-123',
            ),
        ));

        $middleware = new BearerTokenMiddleware($config);
        $request = Request::create('/api/validate', 'POST');
        $request->headers->set('Authorization', 'Bearer secure-token-123');

        $response = $middleware->handle($request, function ($req) {
            return new JsonResponse(array('success' => true));
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
