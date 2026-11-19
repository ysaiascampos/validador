<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Config\Repository as ConfigRepository;
use Vendor\Validator\Middleware\ReportBasicAuthMiddleware;
use Vendor\Validator\Services\ReportLinkSigner;

class ReportBasicAuthMiddlewareTest extends TestCase
{
    public function testDisabledAuthPassesWithoutCredentials()
    {
        $response = $this->run($this->middleware(array('enabled' => false)), Request::create('/validator/report'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function testMissingCredentialsReturnsUnauthorizedWithChallenge()
    {
        $response = $this->run($this->middleware(), Request::create('/validator/report'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(0, strpos((string) $response->headers->get('WWW-Authenticate'), 'Basic realm='));
    }

    public function testInvalidUserReturnsUnauthorized()
    {
        $request = Request::create('/validator/report', 'GET', array(), array(), array(), array(
            'PHP_AUTH_USER' => 'otro',
            'PHP_AUTH_PW'   => 'clave-segura',
        ));

        $this->assertSame(401, $this->run($this->middleware(), $request)->getStatusCode());
    }

    public function testInvalidPasswordReturnsUnauthorized()
    {
        $request = Request::create('/validator/report', 'GET', array(), array(), array(), array(
            'PHP_AUTH_USER' => 'coordinador',
            'PHP_AUTH_PW'   => 'otra-clave',
        ));

        $this->assertSame(401, $this->run($this->middleware(), $request)->getStatusCode());
    }

    public function testCredentialsFromServerVariablesPass()
    {
        $request = Request::create('/validator/report', 'GET', array(), array(), array(), array(
            'PHP_AUTH_USER' => 'coordinador',
            'PHP_AUTH_PW'   => 'clave-segura',
        ));

        $this->assertSame(200, $this->run($this->middleware(), $request)->getStatusCode());
    }

    public function testCredentialsFromAuthorizationHeaderPass()
    {
        $request = Request::create('/validator/report');
        $request->headers->set('Authorization', 'Basic ' . base64_encode('coordinador:clave-segura'));

        $this->assertSame(200, $this->run($this->middleware(), $request)->getStatusCode());
    }

    public function testUnconfiguredCredentialsFailClosed()
    {
        $middleware = $this->middleware(array('user' => '', 'password' => ''));
        $request = Request::create('/validator/report', 'GET', array(), array(), array(), array(
            'PHP_AUTH_USER' => 'coordinador',
            'PHP_AUTH_PW'   => 'clave-segura',
        ));

        $this->assertSame(401, $this->run($middleware, $request)->getStatusCode());
    }

    public function testUnauthorizedResponseDisablesCachingAndReferrers()
    {
        $response = $this->run($this->middleware(), Request::create('/validator/report'));

        $this->assertSame('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }

    public function testSignedPdfDownloadPassesWithoutCredentials()
    {
        $expires = time() + 300;
        $request = Request::create($this->signedUrl('/validator/report/pdf', $expires));

        $this->assertSame(200, $this->run($this->middleware(), $request)->getStatusCode());
    }

    public function testSignedLinkDoesNotOpenTheHtmlReport()
    {
        $expires = time() + 300;
        $request = Request::create($this->signedUrl('/validator/report', $expires));

        $this->assertSame(401, $this->run($this->middleware(), $request)->getStatusCode());
    }

    public function testExpiredSignatureIsRejected()
    {
        $expires = time() - 10;
        $request = Request::create($this->signedUrl('/validator/report/pdf', $expires));

        $this->assertSame(401, $this->run($this->middleware(), $request)->getStatusCode());
    }

    public function testTamperedSignatureIsRejected()
    {
        $expires = time() + 300;
        $request = Request::create('/validator/report/pdf?expires=' . $expires . '&signature=inventada');

        $this->assertSame(401, $this->run($this->middleware(), $request)->getStatusCode());
    }

    /**
     * @param string $path
     * @param int $expires
     * @return string
     */
    private function signedUrl($path, $expires)
    {
        $signer = new ReportLinkSigner('clave-segura', 300);

        return $path . '?expires=' . $expires . '&signature=' . $signer->sign($expires);
    }

    /**
     * @param ReportBasicAuthMiddleware $middleware
     * @param Request $request
     * @return Response
     */
    private function run(ReportBasicAuthMiddleware $middleware, Request $request)
    {
        return $middleware->handle($request, function ($req) {
            return new Response('ok', 200);
        });
    }

    /**
     * @param array $auth
     * @return ReportBasicAuthMiddleware
     */
    private function middleware(array $auth = array())
    {
        $config = new ConfigRepository(array(
            'validator' => array(
                'report' => array(
                    'auth' => array_merge(array(
                        'enabled'  => true,
                        'user'     => 'coordinador',
                        'password' => 'clave-segura',
                        'realm'    => 'Validador RSH',
                    ), $auth),
                ),
            ),
        ));

        return new ReportBasicAuthMiddleware($config);
    }
}
