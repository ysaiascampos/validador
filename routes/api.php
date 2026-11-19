<?php

use Illuminate\Support\Facades\Route;
use Vendor\Validator\Controllers\ValidateController;
use Vendor\Validator\Controllers\WsTesterController;
use Vendor\Validator\Controllers\ReportController;
use Vendor\Validator\Middleware\BearerTokenMiddleware;
use Vendor\Validator\Middleware\ReportBasicAuthMiddleware;

$laravelVersion = \Illuminate\Foundation\Application::VERSION;
$reportMiddleware = (array) app('config')->get('validator.report.middleware', array());

if (version_compare($laravelVersion, '5.0', '>=')) {

    Route::group(array('middleware' => array_merge(array(ReportBasicAuthMiddleware::class), $reportMiddleware)), function () {
        Route::get('validator/report', array(
            'uses' => ReportController::class . '@show',
            'as'   => 'validator.report',
        ));
        Route::get('validator/report/pdf', array(
            'uses' => ReportController::class . '@pdf',
            'as'   => 'validator.report.pdf',
        ));
    });

    Route::get('validator/workbench', array(
        'uses' => WsTesterController::class . '@index',
        'as'   => 'validator.workbench',
    ));

    Route::group(
        array(
            'prefix'     => 'api',
            'middleware' => array(BearerTokenMiddleware::class),
        ),
        function () {
            Route::post('validate', array(
                'uses' => ValidateController::class . '@validate',
                'as'   => 'validator.api.validate',
            ));
            Route::post('ws-test', array(
                'uses' => WsTesterController::class . '@test',
                'as'   => 'validator.api.ws-test',
            ));
        }
    );

} else {

    // Laravel 4.2: el Basic auth del reporte se registra como route filter.
    $router = Route::getFacadeRoot();
    $router->filter('validator.report.auth', function ($route, $request) {
        $middleware = new ReportBasicAuthMiddleware(app('config'));
        $response = $middleware->handle($request, function ($req) {
            return null;
        });

        if ($response !== null) {
            $response->send();
            exit;
        }
    });

    $reportGroup = array('before' => 'validator.report.auth');

    if (!empty($reportMiddleware)) {
        $reportGroup['before'] .= '|' . implode('|', $reportMiddleware);
    }

    Route::group($reportGroup, function () {
        Route::get('validator/report', array(
            'uses' => 'Vendor\\Validator\\Controllers\\ReportController@show',
            'as'   => 'validator.report',
        ));
        Route::get('validator/report/pdf', array(
            'uses' => 'Vendor\\Validator\\Controllers\\ReportController@pdf',
            'as'   => 'validator.report.pdf',
        ));
    });

    Route::get('validator/workbench', array(
        'uses' => 'Vendor\\Validator\\Controllers\\WsTesterController@index',
        'as'   => 'validator.workbench',
    ));

    Route::group(
        array(
            'prefix' => 'api',
            'before' => 'auth.bearer',
        ),
        function () {
            Route::post('validate', array(
                'uses' => 'Vendor\Validator\Controllers\ValidateController@validate',
                'as'   => 'validator.api.validate',
            ));
            Route::post('ws-test', array(
                'uses' => 'Vendor\\Validator\\Controllers\\WsTesterController@test',
                'as'   => 'validator.api.ws-test',
            ));
        }
    );

    $router = Route::getFacadeRoot();
    $router->filter('auth.bearer', function ($route, $request) {
        $middleware = new BearerTokenMiddleware(app('config'));
        $response = $middleware->handle($request, function ($req) {
            return null;
        });

        if ($response !== null && method_exists($response, 'getStatusCode') && $response->getStatusCode() === 401) {
            $response->send();
            exit;
        }
    });
}
