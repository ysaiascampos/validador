<?php

use Illuminate\Support\Facades\Route;
use Vendor\Validator\Controllers\ValidateController;
use Vendor\Validator\Controllers\WsTesterController;
use Vendor\Validator\Middleware\BearerTokenMiddleware;

$laravelVersion = \Illuminate\Foundation\Application::VERSION;

if (version_compare($laravelVersion, '5.0', '>=')) {

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
