<?php

namespace Vendor\Validator\Providers;

use Illuminate\Support\ServiceProvider;
use Vendor\Validator\Contracts\OracleRepositoryInterface;
use Vendor\Validator\Contracts\ConnectionResolverInterface;
use Vendor\Validator\Contracts\EnvironmentProviderInterface;
use Vendor\Validator\Contracts\JsonLoaderInterface;
use Vendor\Validator\Oracle\OracleRepository;
use Vendor\Validator\Adapters\ConnectionResolver;
use Vendor\Validator\Adapters\EnvironmentProvider;
use Vendor\Validator\Adapters\JsonLoader;
use Vendor\Validator\Services\ValidatorService;
use Vendor\Validator\Validators\DatabaseValidator;
use Vendor\Validator\Validators\EnvValidator;
use Vendor\Validator\Validators\GitValidator;
use Vendor\Validator\Controllers\ValidateController;
use Vendor\Validator\Middleware\BearerTokenMiddleware;

class ValidatorServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfiguration();
        $this->loadViews();
        $this->loadRoutes();
        $this->registerMiddleware();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfiguration();
        $this->registerBindings();
        $this->registerValidatorService();
    }

    /**
     * Publish the package configuration.
     *
     * @return void
     */
    private function publishConfiguration()
    {
        $configPath = __DIR__ . '/../../config/validator.php';

        if (method_exists($this, 'publishes')) {
            $this->publishes(
                array($configPath => config_path('validator.php')),
                'validator-config'
            );
        }

        if (method_exists($this, 'package')) {
            $this->package('vendor/validator', 'validator', __DIR__ . '/../../config');
        }
    }

    /**
     * Merge the package configuration with the application configuration.
     *
     * @return void
     */
    private function mergeConfiguration()
    {
        $configPath = __DIR__ . '/../../config/validator.php';

        if (method_exists($this, 'mergeConfigFrom')) {
            $this->mergeConfigFrom($configPath, 'validator');
            return;
        }

        // Laravel 4.2: no existe mergeConfigFrom ni el helper env() (agregado
        // en 5.0). El config se lee desde el directorio app/config del paquete
        // publicado (o del archivo manual) y, en su defecto, desde el config
        // por defecto de la libreria.
        if (!function_exists('env')) {
            function env($key, $default = null) {
                if (isset($_ENV[$key])) {
                    return $_ENV[$key];
                }
                if (isset($_SERVER[$key])) {
                    return $_SERVER[$key];
                }
                $value = getenv($key);
                return $value === false ? $default : $value;
            }
        }

        $paths = array(
            base_path('app/config/packages/vendor/validator/validator.php'),
            base_path('app/config/validator.php'),
            $configPath,
        );

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->app['config']->set('validator', require $path);
                return;
            }
        }
    }

    /**
     * Resolve the .env file path used as source of truth.
     *
     * Prefiere .env (Laravel 5+). Si no existe, busca los archivos de
     * Laravel 4.2: .env.{environment}.php, .env.{environment} y .env.php.
     *
     * @param mixed $app
     * @return string
     */
    private function detectEnvFilePath($app)
    {
        $default = base_path('.env');

        if (file_exists($default)) {
            return $default;
        }

        $environment = method_exists($app, 'environment') ? $app->environment() : null;

        $candidates = array(
            base_path('.env.' . $environment . '.php'),
            base_path('.env.' . $environment),
            base_path('.env.php'),
        );

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return $default;
    }

    /**
     * Register dependency injection bindings.
     *
     * @return void
     */
    private function registerBindings()
    {
        $this->app->singleton(ConnectionResolverInterface::class, function ($app) {
            return new ConnectionResolver();
        });

        $this->app->singleton(EnvironmentProviderInterface::class, function ($app) {
            return new EnvironmentProvider();
        });

        $this->app->singleton(JsonLoaderInterface::class, function ($app) {
            return new JsonLoader();
        });

        $this->app->singleton(OracleRepositoryInterface::class, function ($app) {
            $resolver = $app->make(ConnectionResolverInterface::class);
            $defaultConnection = $app->make('config')->get('validator.default_connection', 'oracle');
            return new OracleRepository($resolver, $defaultConnection);
        });

        $this->app->singleton('validator.middleware.bearer', function ($app) {
            return new BearerTokenMiddleware($app['config']);
        });

        $this->registerValidatorBindings();
    }

    /**
     * Register the validator classes with their configuration injected.
     *
     * @return void
     */
    private function registerValidatorBindings()
    {
        $this->app->bind(DatabaseValidator::class, function ($app) {
            return new DatabaseValidator(
                $app->make(OracleRepositoryInterface::class),
                $app->make('config')->get('validator', array())
            );
        });

        $this->app->bind(EnvValidator::class, function ($app) {
            $config = $app->make('config')->get('validator.env', array());

            if (!isset($config['env_file']) || $config['env_file'] === null || $config['env_file'] === '') {
                $config['env_file'] = $this->detectEnvFilePath($app);
            }

            return new EnvValidator(
                $app->make(EnvironmentProviderInterface::class),
                $config
            );
        });

        $this->app->bind(GitValidator::class, function ($app) {
            return new GitValidator(
                base_path(),
                $app->make('config')->get('validator.git', array())
            );
        });
    }

    /**
     * Register the validator service with auto-discovery.
     *
     * @return void
     */
    private function registerValidatorService()
    {
        $this->app->singleton(ValidatorService::class, function ($app) {
            $service = new ValidatorService(
                'Vendor\\Validator\\Validators\\',
                __DIR__ . '/../Validators'
            );

            $service->setApplication($app);

            $service->getValidators();

            return $service;
        });
    }

    /**
     * Load the package routes.
     *
     * @return void
     */
    private function loadRoutes()
    {
        $routesPath = __DIR__ . '/../../routes/api.php';

        if (!file_exists($routesPath)) {
            return;
        }

        $parentClass = get_parent_class($this);
        $parentReflection = new \ReflectionClass($parentClass);

        if ($parentReflection->hasMethod('loadRoutesFrom')) {
            parent::loadRoutesFrom($routesPath);
            return;
        }

        $router = $this->app['router'];

        if (method_exists($router, 'group')) {
            require $routesPath;
        }
    }

    /**
     * Register the package views when the host Laravel version supports it.
     *
     * @return void
     */
    private function loadViews()
    {
        $viewsPath = __DIR__ . '/../../resources/views';

        if (method_exists($this, 'loadViewsFrom')) {
            $this->loadViewsFrom($viewsPath, 'validator');
            return;
        }

        // Laravel 4.2: register the package view namespace manually.
        if (isset($this->app['view']) && method_exists($this->app['view'], 'addNamespace')) {
            $this->app['view']->addNamespace('validator', $viewsPath);
            return;
        }

        if (class_exists('Illuminate\\Support\\Facades\\View')) {
            \Illuminate\Support\Facades\View::addNamespace('validator', $viewsPath);
        }
    }

    /**
     * Register the BearerToken middleware with the router.
     *
     * @return void
     */
    private function registerMiddleware()
    {
        $router = $this->app['router'];

        if (method_exists($router, 'aliasMiddleware')) {
            $router->aliasMiddleware('auth.bearer', BearerTokenMiddleware::class);
        } elseif (method_exists($router, 'middleware')) {
            $router->middleware('auth.bearer', BearerTokenMiddleware::class);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            ConnectionResolverInterface::class,
            EnvironmentProviderInterface::class,
            JsonLoaderInterface::class,
            OracleRepositoryInterface::class,
            ValidatorService::class,
        );
    }

}
