<?php

namespace Vendor\Validator\Services;

use Vendor\Validator\Contracts\ValidatorInterface;
use Vendor\Validator\DTO\ValidationResult;

class ValidatorService
{
    /**
     * Registered validator instances.
     *
     * @var ValidatorInterface[]
     */
    private $validators;

    /**
     * Base namespace for auto-discovery.
     *
     * @var string
     */
    private $discoveryNamespace;

    /**
     * Base path for auto-discovery.
     *
     * @var string
     */
    private $discoveryPath;

    /**
     * Application container for dependency resolution.
     *
     * @var \Illuminate\Container\Container|null
     */
    private $app;

    /**
     * Constructor.
     *
     * @param string $discoveryNamespace
     * @param string $discoveryPath
     */
    public function __construct($discoveryNamespace = '', $discoveryPath = '')
    {
        $this->validators = array();
        $this->discoveryNamespace = $discoveryNamespace ?: 'Vendor\\Validator\\Validators\\';
        $this->discoveryPath = $discoveryPath ?: dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Validators';
        $this->app = null;
    }

    /**
     * Set the application container.
     *
     * @param \Illuminate\Container\Container $app
     * @return void
     */
    public function setApplication($app)
    {
        $this->app = $app;
    }

    /**
     * Register a single validator.
     *
     * @param ValidatorInterface $validator
     * @return void
     */
    public function registerValidator(ValidatorInterface $validator)
    {
        $this->validators[$validator->getName()] = $validator;
    }

    /**
     * Register multiple validators.
     *
     * @param ValidatorInterface[] $validators
     * @return void
     */
    public function registerValidators(array $validators)
    {
        foreach ($validators as $validator) {
            $this->registerValidator($validator);
        }
    }

    /**
     * Get all registered validators.
     *
     * @return ValidatorInterface[]
     */
    public function getValidators()
    {
        if (empty($this->validators)) {
            $this->discoverValidators();
        }

        return $this->validators;
    }

    /**
     * Get a specific validator by name.
     *
     * @param string $name
     * @return ValidatorInterface|null
     */
    public function getValidator($name)
    {
        $validators = $this->getValidators();

        return isset($validators[$name]) ? $validators[$name] : null;
    }

    /**
     * Auto-discover validators from the filesystem.
     *
     * @return void
     */
    private function discoverValidators()
    {
        if (!is_dir($this->discoveryPath)) {
            return;
        }

        $iterator = new \DirectoryIterator($this->discoveryPath);

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDot() || $fileinfo->isDir()) {
                continue;
            }

            $filename = $fileinfo->getFilename();

            if (!preg_match('/^(.+)\.php$/', $filename, $matches)) {
                continue;
            }

            $className = $this->discoveryNamespace . $matches[1];
            $this->instantiateValidator($className);
        }
    }

    /**
     * Instantiate a validator class.
     *
     * @param string $className
     * @return void
     */
    private function instantiateValidator($className)
    {
        if (!class_exists($className)) {
            return;
        }

        $reflection = new \ReflectionClass($className);

        if (!$reflection->implementsInterface(ValidatorInterface::class)) {
            return;
        }

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return;
        }

        try {
            if ($this->app !== null) {
                $instance = $this->app->make($className);
            } else {
                $constructor = $reflection->getConstructor();

                if ($constructor === null) {
                    $instance = $reflection->newInstance();
                } else {
                    $parameters = $constructor->getParameters();
                    $dependencies = $this->resolveDependencies($parameters);
                    $instance = $reflection->newInstanceArgs($dependencies);
                }
            }

            if ($instance instanceof ValidatorInterface) {
                $this->registerValidator($instance);
            }
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * Resolve constructor dependencies.
     *
     * @param \ReflectionParameter[] $parameters
     * @return array
     */
    private function resolveDependencies(array $parameters)
    {
        $dependencies = array();

        foreach ($parameters as $parameter) {
            $paramClass = $parameter->getClass();

            if ($paramClass !== null) {
                if ($this->app !== null) {
                    $dependencies[] = $this->app->make($paramClass->getName());
                } else {
                    try {
                        $dependencies[] = $this->resolveClass($paramClass->getName());
                    } catch (\Exception $e) {
                        if ($parameter->isDefaultValueAvailable()) {
                            $dependencies[] = $parameter->getDefaultValue();
                        } else {
                            $dependencies[] = null;
                        }
                    }
                }
            } else {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    $dependencies[] = null;
                }
            }
        }

        return $dependencies;
    }

    /**
     * Resolve a class by name using reflection.
     *
     * @param string $className
     * @return object
     */
    private function resolveClass($className)
    {
        $reflection = new \ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException(sprintf('Class %s is not instantiable', $className));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Run all validators and consolidate results.
     *
     * @param array $options
     * @return ValidationResult
     */
    public function validate(array $options = array())
    {
        $startTime = microtime(true);
        $consolidated = new ValidationResult('consolidated');

        $validators = $this->getValidators();

        if (empty($validators)) {
            $consolidated->addWarning('No validators found to execute.');
            $consolidated->setExecutionTime(0.0);
            return $consolidated;
        }

        $enableDatabase = isset($options['database']) ? $options['database'] : true;
        $enableEnv = isset($options['env']) ? $options['env'] : true;
        $enableGit = isset($options['git']) ? $options['git'] : true;

        foreach ($validators as $name => $validator) {
            if ($name === 'database' && !$enableDatabase) {
                continue;
            }

            if ($name === 'env' && !$enableEnv) {
                continue;
            }

            if ($name === 'git' && !$enableGit) {
                continue;
            }

            try {
                $validatorOptions = (isset($options[$name]) && is_array($options[$name]))
                    ? $options[$name]
                    : array();

                $result = $validator->validate($validatorOptions);
                $consolidated->mergeStatus($result);
                $consolidated->addDetail($name, $result->toArray());
            } catch (\Exception $e) {
                $errorResult = new ValidationResult($name);
                $errorResult->addError(sprintf(
                    'Validator %s threw an exception: %s',
                    $name,
                    $e->getMessage()
                ));
                $consolidated->mergeStatus($errorResult);
                $consolidated->addDetail($name, $errorResult->toArray());
            }
        }

        $executionTime = (microtime(true) - $startTime) * 1000;
        $consolidated->setExecutionTime($executionTime);

        return $consolidated;
    }

    /**
     * Run a specific validator by name.
     *
     * @param string $name
     * @param array $options
     * @return ValidationResult
     */
    public function validateSpecific($name, array $options = array())
    {
        $validator = $this->getValidator($name);

        if ($validator === null) {
            $result = new ValidationResult($name);
            $result->addError(sprintf('Validator not found: %s', $name));
            return $result;
        }

        return $validator->validate($options);
    }
}
