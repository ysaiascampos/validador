<?php

namespace Vendor\Validator\Validators;

use Vendor\Validator\Contracts\ValidatorInterface;
use Vendor\Validator\Contracts\EnvironmentProviderInterface;
use Vendor\Validator\DTO\ValidationResult;

class EnvValidator implements ValidatorInterface
{
    /**
     * The environment provider instance.
     *
     * @var EnvironmentProviderInterface
     */
    private $envProvider;

    /**
     * Validation configuration.
     *
     * @var array
     */
    private $config;

    /**
     * Per-variable status map for the current validation run.
     *
     * @var array
     */
    private $status;

    /**
     * Provider that reads the .env file directly (source of truth).
     *
     * @var \Vendor\Validator\Contracts\EnvironmentProviderInterface|null
     */
    private $fileProvider;

    /**
     * Built-in default required variables, used when no config is provided.
     *
     * @var array
     */
    private $defaultRequired = array(
        'APP_ENV',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_CHARSET',
        'DB_EXPORT_CONNECTION',
        'DB_EXPORT_HOST',
        'DB_EXPORT_PORT',
        'DB_EXPORT_DATABASE',
        'DB_EXPORT_USERNAME',
        'DB_EXPORT_CHARSET',
    );

    /**
     * Constructor.
     *
     * @param EnvironmentProviderInterface $envProvider
     * @param array $config
     */
    public function __construct(EnvironmentProviderInterface $envProvider, array $config = array())
    {
        $this->envProvider = $envProvider;
        $this->config = $this->applyDefaults($config);
        $this->status = array();
        $this->fileProvider = $this->resolveFileProvider($config);
    }

    /**
     * Build a file provider when the config points to an existing .env file.
     *
     * @param array $config
     * @return EnvironmentProviderInterface|null
     */
    private function resolveFileProvider(array $config)
    {
        $path = isset($config['env_file']) ? $config['env_file'] : null;

        if (!is_string($path) || $path === '' || !file_exists($path) || !is_file($path)) {
            return null;
        }

        return new \Vendor\Validator\Adapters\EnvFileProvider($path);
    }

    /**
     * Apply built-in defaults only when a section is not defined at all.
     * An explicit empty list is respected as-is.
     *
     * @param array $config
     * @return array
     */
    private function applyDefaults(array $config)
    {
        if (!isset($config['required'])) {
            $config['required'] = $this->defaultRequired;
        }

        if (!isset($config['optional'])) {
            $config['optional'] = array();
        }

        if (!isset($config['rules'])) {
            $config['rules'] = array();
        }

        return $config;
    }

    /**
     * Get the validator name.
     *
     * @return string
     */
    public function getName()
    {
        return 'env';
    }

    /**
     * Execute environment validation.
     *
     * @param array $options
     * @return ValidationResult
     */
    public function validate(array $options = array())
    {
        $startTime = microtime(true);
        $result = new ValidationResult($this->getName());
        $this->status = array();

        try {
            $rules = $this->resolveRules($options);
            $rules = $this->applyEnvironmentRules($rules, $this->resolveEnvironment($options));
            $rules = $this->rejectSensitiveVariables($result, $rules);

            if ($this->isEmptyRules($rules)) {
                $result->addWarning('No environment variables configured for validation.');
            } else {
                $this->validateRequired($result, $rules);
                $this->validateOptional($result, $rules);
                $this->validateRules($result, $rules);
            }

            $this->validateFileConsistency($result, $rules);
            $this->validateFileDiscovery($result, $rules);

            $result->addDetail('env_status', $this->status);
            $this->addShownValues($result);

        } catch (\Exception $e) {
            $result->addError(sprintf('Environment validation error: %s', $e->getMessage()));
        }

        $executionTime = (microtime(true) - $startTime) * 1000;
        $result->setExecutionTime($executionTime);

        return $result;
    }

    /**
     * Add the loaded value of selected variables to the result details.
     *
     * Variables are listed in the config option 'show_values'. Sensitive
     * variables are never shown.
     *
     * @param ValidationResult $result
     * @return void
     */
    private function addShownValues(ValidationResult $result)
    {
        $show = isset($this->config['show_values']) && is_array($this->config['show_values'])
            ? $this->config['show_values']
            : array();

        if (empty($show)) {
            return;
        }

        $values = array();

        foreach ($show as $name) {
            if (!is_string($name) || $this->isSensitiveVariable($name)) {
                continue;
            }

            if (!$this->envProvider->has($name)) {
                continue;
            }

            $values[$name] = $this->envProvider->get($name);
        }

        if (!empty($values)) {
            $result->addDetail('env_values', $values);
        }
    }

    /**
     * Resolve the rules structure from validation options.
     *
     * Supports three forms:
     *  - flat structure: array('required' => array(), 'optional' => array(), 'rules' => array())
     *  - wrapped structure: array('rules' => array('required' => array(), ...))
     *  - bare rules map: array('rules' => array('VAR' => array('equals' => 'x')))
     *
     * @param array $options
     * @return array
     */
    private function resolveRules(array $options)
    {
        if (isset($options['rules']) && is_array($options['rules'])
            && !isset($options['required']) && !isset($options['optional'])) {
            $wrapped = $options['rules'];

            if (isset($wrapped['required']) || isset($wrapped['optional']) || isset($wrapped['rules'])) {
                return $wrapped;
            }

            return array('rules' => $wrapped);
        }

        if (empty($options)) {
            return $this->config;
        }

        return $options;
    }

    /**
     * Determine the current environment name.
     *
     * Resolution order: request option -> config -> APP_ENV.
     *
     * @param array $options
     * @return string|null
     */
    private function resolveEnvironment(array $options)
    {
        if (isset($options['environment']) && is_string($options['environment']) && $options['environment'] !== '') {
            return $options['environment'];
        }

        if (isset($this->config['environment']) && is_string($this->config['environment']) && $this->config['environment'] !== '') {
            return $this->config['environment'];
        }

        $appEnv = $this->envProvider->get('APP_ENV');

        if (is_string($appEnv) && $appEnv !== '') {
            return $appEnv;
        }

        return null;
    }

    /**
     * Overlay the rules of the current environment on top of the base rules.
     *
     * If the environment block defines 'required' or 'optional', they replace
     * the base sections for that environment. 'rules' are merged per variable,
     * so an environment can override or add specific variable rules.
     *
     * @param array $rules
     * @param string|null $environment
     * @return array
     */
    private function applyEnvironmentRules(array $rules, $environment)
    {
        $environments = isset($this->config['environments']) && is_array($this->config['environments'])
            ? $this->config['environments']
            : array();

        if ($environment === null || $environment === '' || !isset($environments[$environment])) {
            return $rules;
        }

        $envRules = $environments[$environment];

        if (!is_array($envRules)) {
            return $rules;
        }

        $merged = $rules;

        foreach (array('required', 'optional') as $section) {
            if (isset($envRules[$section]) && is_array($envRules[$section])) {
                $merged[$section] = $envRules[$section];
            }
        }

        if (isset($envRules['rules']) && is_array($envRules['rules'])) {
            $baseRules = isset($merged['rules']) && is_array($merged['rules']) ? $merged['rules'] : array();
            $merged['rules'] = array_merge($baseRules, $envRules['rules']);
        }

        return $merged;
    }

    /**
     * Determine whether the resolved rules contain nothing to validate.
     *
     * @param array $rules
     * @return bool
     */    private function isEmptyRules(array $rules)
    {
        $required = isset($rules['required']) && is_array($rules['required']) ? $rules['required'] : array();
        $optional = isset($rules['optional']) && is_array($rules['optional']) ? $rules['optional'] : array();
        $varRules = isset($rules['rules']) && is_array($rules['rules']) ? $rules['rules'] : array();

        return empty($required) && empty($optional) && empty($varRules);
    }

    /**
     * Record the status of an environment variable check.
     *
     * @param string $name
     * @param string $status
     * @return void
     */
    private function markStatus($name, $status)
    {
        $this->status[$name] = $status;
    }

    /**
     * Reject variables whose name contains a sensitive keyword
     * (PASSWORD, TOKEN, SECRET, KEY) and remove them from the rules.
     *
     * @param ValidationResult $result
     * @param array $rules
     * @return array
     */
    private function rejectSensitiveVariables(ValidationResult $result, array $rules)
    {
        $sensitive = $this->findSensitiveVariables($rules);

        foreach ($sensitive as $varName) {
            $result->addError(sprintf(
                'Sensitive environment variable cannot be validated: %s',
                $varName
            ));
        }

        if (empty($sensitive)) {
            return $rules;
        }

        $safeRules = $rules;

        if (isset($safeRules['required']) && is_array($safeRules['required'])) {
            $safeRules['required'] = array_values(array_filter(
                $safeRules['required'],
                function ($var) use ($sensitive) {
                    $name = is_string($var) ? $var : (isset($var['name']) ? $var['name'] : null);
                    return !in_array($name, $sensitive, true);
                }
            ));
        }

        if (isset($safeRules['optional']) && is_array($safeRules['optional'])) {
            $safeRules['optional'] = array_values(array_filter(
                $safeRules['optional'],
                function ($var) use ($sensitive) {
                    $name = is_string($var) ? $var : (isset($var['name']) ? $var['name'] : null);
                    return !in_array($name, $sensitive, true);
                }
            ));
        }

        if (isset($safeRules['rules']) && is_array($safeRules['rules'])) {
            foreach ($sensitive as $varName) {
                if (isset($safeRules['rules'][$varName])) {
                    unset($safeRules['rules'][$varName]);
                }
            }
        }

        return $safeRules;
    }

    /**
     * Collect all variable names requested for validation that contain
     * a sensitive keyword.
     *
     * @param array $rules
     * @return array
     */
    private function findSensitiveVariables(array $rules)
    {
        $names = array();

        if (isset($rules['required']) && is_array($rules['required'])) {
            foreach ($rules['required'] as $var) {
                $names[] = is_string($var) ? $var : (isset($var['name']) ? $var['name'] : null);
            }
        }

        if (isset($rules['optional']) && is_array($rules['optional'])) {
            foreach ($rules['optional'] as $var) {
                $names[] = is_string($var) ? $var : (isset($var['name']) ? $var['name'] : null);
            }
        }

        if (isset($rules['rules']) && is_array($rules['rules'])) {
            foreach (array_keys($rules['rules']) as $name) {
                $names[] = $name;
            }
        }

        $sensitive = array();
        foreach ($names as $name) {
            if ($name !== null && $this->isSensitiveVariable($name)) {
                $sensitive[] = $name;
            }
        }

        return array_values(array_unique($sensitive));
    }

    /**
     * Determine whether a variable name contains a sensitive keyword.
     *
     * @param string $name
     * @return bool
     */
    private function isSensitiveVariable($name)
    {
        $sensitiveWords = array('password', 'token', 'secret', 'key');
        $parts = preg_split('/[^a-zA-Z0-9]+/', $name);

        foreach ($parts as $part) {
            if (in_array(strtolower($part), $sensitiveWords, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate required variables exist.
     *
     * @param ValidationResult $result
     * @param array $rules
     * @return void
     */
    private function validateRequired(ValidationResult $result, array $rules)
    {
        $required = isset($rules['required']) ? $rules['required'] : array();

        if (isset($rules['required']) && is_array($rules['required'])) {
            $required = $rules['required'];
        }

        foreach ($required as $variable) {
            $varName = is_string($variable) ? $variable : (isset($variable['name']) ? $variable['name'] : null);

            if (is_null($varName)) {
                continue;
            }

            if (!$this->envProvider->has($varName)) {
                if ($this->fileHas($varName)) {
                    $result->addWarning(sprintf(
                        'Environment variable %s is defined in the .env file but is not loaded in the current process.',
                        $varName
                    ));
                    $this->markStatus($varName, 'not_loaded');
                } else {
                    $result->addError(sprintf('Required environment variable is missing: %s', $varName));
                    $this->markStatus($varName, 'missing');
                }
                continue;
            }

            $value = $this->envProvider->get($varName);

            if (is_null($value) || $value === '') {
                $result->addError(sprintf('Required environment variable is empty: %s', $varName));
                $this->markStatus($varName, 'empty');
            } else {
                $this->markStatus($varName, 'ok');
            }
        }
    }

    /**
     * Validate optional variables and apply rules if present.
     *
     * @param ValidationResult $result
     * @param array $rules
     * @return void
     */
    private function validateOptional(ValidationResult $result, array $rules)
    {
        $optional = isset($rules['optional']) ? $rules['optional'] : array();

        foreach ($optional as $variable) {
            $varName = is_string($variable) ? $variable : (isset($variable['name']) ? $variable['name'] : null);

            if (is_null($varName)) {
                continue;
            }

            if ($this->envProvider->has($varName)) {
                $value = $this->envProvider->get($varName);
                $this->markStatus($varName, 'ok');

                if (is_array($variable) && isset($variable['rules'])) {
                    $this->applyVariableRules($result, $varName, $value, $variable['rules']);
                }
            }
        }
    }

    /**
     * Apply validation rules to environment variables.
     *
     * @param ValidationResult $result
     * @param array $rules
     * @return void
     */
    private function validateRules(ValidationResult $result, array $rules)
    {
        $variableRules = isset($rules['rules']) ? $rules['rules'] : array();

        foreach ($variableRules as $varName => $varRules) {
            if (!$this->envProvider->has($varName)) {
                if ($this->fileHas($varName)) {
                    $result->addWarning(sprintf(
                        'Environment variable %s is defined in the .env file but is not loaded in the current process.',
                        $varName
                    ));
                    $this->markStatus($varName, 'not_loaded');
                } else {
                    $result->addWarning(sprintf('Environment variable not found for rule validation: %s', $varName));
                    $this->markStatus($varName, 'missing');
                }
                continue;
            }

            $value = $this->envProvider->get($varName);
            $this->markStatus($varName, 'ok');
            $this->applyVariableRules($result, $varName, $value, $varRules);
        }
    }

    /**
     * Check whether a variable is defined in the .env file.
     *
     * @param string $name
     * @return bool
     */
    private function fileHas($name)
    {
        return $this->fileProvider !== null && $this->fileProvider->has($name);
    }

    /**
     * Collect all variable names referenced by the rules.
     *
     * @param array $rules
     * @return array
     */
    private function collectRuleNames(array $rules)
    {
        $names = array();

        foreach (array('required', 'optional') as $section) {
            if (isset($rules[$section]) && is_array($rules[$section])) {
                foreach ($rules[$section] as $var) {
                    $names[] = is_string($var) ? $var : (isset($var['name']) ? $var['name'] : null);
                }
            }
        }

        if (isset($rules['rules']) && is_array($rules['rules'])) {
            foreach (array_keys($rules['rules']) as $name) {
                $names[] = $name;
            }
        }

        return array_values(array_filter($names));
    }

    /**
     * Detect stale or not-loaded variables by comparing the loaded values
     * against the .env file.
     *
     * @param ValidationResult $result
     * @param array $rules
     * @return void
     */
    private function validateFileConsistency(ValidationResult $result, array $rules)
    {
        if ($this->fileProvider === null) {
            return;
        }

        $names = $this->collectRuleNames($rules);

        foreach ($names as $name) {
            if (!$this->fileProvider->has($name)) {
                continue;
            }

            $loaded = $this->envProvider->get($name);
            $fileValue = $this->fileProvider->get($name);

            if (is_null($loaded) || $loaded === '') {
                continue;
            }

            if (!$this->valuesEquivalent($loaded, $fileValue)) {
                $result->addWarning(sprintf(
                    'Environment variable %s differs from the .env file (loaded: "%s", file: "%s").',
                    $name,
                    $loaded,
                    $fileValue
                ));
                $this->markStatus($name, 'stale');
            }
        }
    }

    /**
     * Auto-discover variables defined in the .env file and validate them
     * against the loaded values, even when they are not listed in the rules.
     * Sensitive variables are skipped without exposing their values.
     *
     * @param ValidationResult $result
     * @param array $rules
     * @return array
     */
    private function validateFileDiscovery(ValidationResult $result, array $rules)
    {
        if ($this->fileProvider === null) {
            return array();
        }

        $enabled = isset($this->config['auto_discover']) && $this->config['auto_discover'];

        if (!$enabled) {
            return array();
        }

        $ruleNames = $this->collectRuleNames($rules);
        $discovered = array();
        $sensitiveSkipped = 0;

        foreach ($this->fileProvider->all() as $name => $fileValue) {
            if (in_array($name, $ruleNames, true)) {
                continue;
            }

            if ($this->isSensitiveVariable($name)) {
                $sensitiveSkipped++;
                continue;
            }

            $discovered[] = $name;
            $loaded = $this->envProvider->get($name);

            if (is_null($loaded)) {
                $result->addWarning(sprintf(
                    'Environment variable %s is defined in the .env file but is not loaded in the current process.',
                    $name
                ));
                $this->markStatus($name, 'not_loaded');
                continue;
            }

            if (!$this->valuesEquivalent($loaded, $fileValue)) {
                $result->addWarning(sprintf(
                    'Environment variable %s differs from the .env file (loaded: "%s", file: "%s").',
                    $name,
                    $loaded,
                    $fileValue
                ));
                $this->markStatus($name, 'stale');
                continue;
            }

            $this->markStatus($name, 'ok');
        }

        if ($sensitiveSkipped > 0) {
            $result->addDetail('env_sensitive_skipped', $sensitiveSkipped);
        }

        return $discovered;
    }

    /**
     * Compare two loaded/file values, treating equivalent boolean and
     * numeric representations ("1" vs "true", "1521" vs "1521.0") as equal.
     *
     * @param mixed $loaded
     * @param mixed $fileValue
     * @return bool
     */
    private function valuesEquivalent($loaded, $fileValue)
    {
        $loaded = (string) $loaded;
        $fileValue = (string) $fileValue;

        if ($loaded === $fileValue) {
            return true;
        }

        $loadedBool = $this->normalizeBoolean($loaded);
        $fileBool = $this->normalizeBoolean($fileValue);

        if ($loadedBool !== null && $fileBool !== null) {
            return $loadedBool === $fileBool;
        }

        if (is_numeric($loaded) && is_numeric($fileValue)) {
            return (float) $loaded === (float) $fileValue;
        }

        return false;
    }

    /**
     * Normalize a value to a boolean when it represents one, or null otherwise.
     *
     * @param string $value
     * @return bool|null
     */
    private function normalizeBoolean($value)
    {
        $lower = strtolower(trim($value));

        if (in_array($lower, array('true', '1', 'yes', 'on'), true)) {
            return true;
        }

        if (in_array($lower, array('false', '0', 'no', 'off', ''), true)) {
            return false;
        }

        return null;
    }

    /**
     * Apply rules to a single variable.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $value
     * @param array $varRules
     * @return void
     */
    private function applyVariableRules(ValidationResult $result, $varName, $value, array $varRules)
    {
        foreach ($varRules as $rule => $ruleValue) {
            switch ($rule) {
                case 'equals':
                    $this->validateEquals($result, $varName, $value, $ruleValue);
                    break;

                case 'contains':
                    $this->validateContains($result, $varName, $value, $ruleValue);
                    break;

                case 'csv':
                    $this->validateCsv($result, $varName, $value, $ruleValue);
                    break;

                case 'json':
                    $this->validateJson($result, $varName, $value, $ruleValue);
                    break;

                case 'array':
                    $this->validateArray($result, $varName, $value, $ruleValue);
                    break;

                case 'boolean':
                    $this->validateBoolean($result, $varName, $value, $ruleValue);
                    break;

                case 'numeric':
                    $this->validateNumeric($result, $varName, $value, $ruleValue);
                    break;

                case 'min':
                    $this->validateMin($result, $varName, $value, $ruleValue);
                    break;

                case 'max':
                    $this->validateMax($result, $varName, $value, $ruleValue);
                    break;

                case 'regex':
                    $this->validateRegex($result, $varName, $value, $ruleValue);
                    break;

                default:
                    $result->addWarning(sprintf('Unknown validation rule "%s" for variable: %s', $rule, $varName));
                    break;
            }
        }
    }

    /**
     * Validate that a value equals an expected value.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $value
     * @param mixed $expected
     * @return void
     */
    private function validateEquals(ValidationResult $result, $varName, $value, $expected)
    {
        if ((string) $value !== (string) $expected) {
            $result->addError(sprintf(
                'Environment variable %s equals "%s", expected "%s"',
                $varName,
                $value,
                $expected
            ));
        }
    }

    /**
     * Validate that a value contains a substring.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $value
     * @param string $needle
     * @return void
     */
    private function validateContains(ValidationResult $result, $varName, $value, $needle)
    {
        if (strpos((string) $value, (string) $needle) === false) {
            $result->addError(sprintf(
                'Environment variable %s does not contain expected value: %s',
                $varName,
                $needle
            ));
        }
    }

    /**
     * Validate a CSV-formatted environment variable.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $value
     * @param array $expected
     * @return void
     */
    private function validateCsv(ValidationResult $result, $varName, $value, $expected)
    {
        $items = explode(',', (string) $value);
        $items = array_map('trim', $items);
        $items = array_values(array_filter($items, function ($item) {
            return $item !== '';
        }));

        $ignoreCase = isset($expected['ignore_case']) && $expected['ignore_case'];

        if (isset($expected['count']) && count($items) !== (int) $expected['count']) {
            $result->addError(sprintf(
                'Environment variable %s CSV count is %d, expected %d',
                $varName,
                count($items),
                $expected['count']
            ));
        }

        if (isset($expected['contains'])) {
            $required = is_array($expected['contains']) ? $expected['contains'] : array($expected['contains']);
            $haystack = $ignoreCase ? array_map('strtolower', $items) : $items;

            foreach ($required as $requiredItem) {
                $needle = (string) $requiredItem;
                if ($ignoreCase) {
                    $needle = strtolower($needle);
                }

                if (!in_array($needle, $haystack, true)) {
                    $result->addError(sprintf(
                        'Environment variable %s CSV is missing required item: %s',
                        $varName,
                        $requiredItem
                    ));
                }
            }
        }
    }

    /**
     * Validate a JSON-formatted environment variable.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $value
     * @param array $expected
     * @return void
     */
    private function validateJson(ValidationResult $result, $varName, $value, $expected)
    {
        $decoded = json_decode((string) $value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $result->addError(sprintf(
                'Environment variable %s is not valid JSON: %s',
                $varName,
                json_last_error_msg()
            ));
            return;
        }

        if (isset($expected['keys'])) {
            $requiredKeys = is_array($expected['keys']) ? $expected['keys'] : array($expected['keys']);
            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $decoded)) {
                    $result->addError(sprintf(
                        'Environment variable %s JSON is missing required key: %s',
                        $varName,
                        $key
                    ));
                }
            }
        }

        if (isset($expected['schema']) && is_array($expected['schema'])) {
            $this->validateJsonStructure($result, $varName, $decoded, $expected['schema'], '');
        }
    }

    /**
     * Recursively validate JSON structure.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $data
     * @param array $schema
     * @param string $path
     * @return void
     */
    private function validateJsonStructure(ValidationResult $result, $varName, $data, array $schema, $path)
    {
        foreach ($schema as $key => $rules) {
            $currentPath = empty($path) ? $key : $path . '.' . $key;

            if (!array_key_exists($key, $data)) {
                if (isset($rules['required']) && $rules['required']) {
                    $result->addError(sprintf(
                        'Environment variable %s JSON is missing required key at path: %s',
                        $varName,
                        $currentPath
                    ));
                }
                continue;
            }

            $value = $data[$key];

            if (isset($rules['type'])) {
                $actualType = gettype($value);
                if ($actualType !== $rules['type']) {
                    $result->addError(sprintf(
                        'Environment variable %s JSON path %s has type %s, expected %s',
                        $varName,
                        $currentPath,
                        $actualType,
                        $rules['type']
                    ));
                }
            }

            if (isset($rules['type']) && $rules['type'] === 'array' && is_array($rules['items'])) {
                foreach ($value as $index => $item) {
                    $itemPath = $currentPath . '[' . $index . ']';
                    if (is_array($item) && is_array($rules['items'])) {
                        $this->validateJsonStructure($result, $varName, $item, $rules['items'], $itemPath);
                    }
                }
            }
        }
    }

    /**
     * Validate an array-formatted environment variable.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $value
     * @param array $expected
     * @return void
     */
    private function validateArray(ValidationResult $result, $varName, $value, $expected)
    {
        if (!is_array($value)) {
            $result->addError(sprintf(
                'Environment variable %s is not an array',
                $varName
            ));
            return;
        }

        if (isset($expected['count']) && count($value) !== (int) $expected['count']) {
            $result->addError(sprintf(
                'Environment variable %s array count is %d, expected %d',
                $varName,
                count($value),
                $expected['count']
            ));
        }

        if (isset($expected['keys'])) {
            $requiredKeys = is_array($expected['keys']) ? $expected['keys'] : array($expected['keys']);
            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $value)) {
                    $result->addError(sprintf(
                        'Environment variable %s array is missing key: %s',
                        $varName,
                        $key
                    ));
                }
            }
        }
    }

    /**
     * Validate a boolean-formatted environment variable.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $value
     * @param mixed $expected
     * @return void
     */
    private function validateBoolean(ValidationResult $result, $varName, $value, $expected)
    {
        $stringValue = strtolower((string) $value);
        $validBooleans = array('true', 'false', '1', '0', 'yes', 'no');

        if (!in_array($stringValue, $validBooleans, true)) {
            $result->addError(sprintf(
                'Environment variable %s is not a valid boolean, got: %s',
                $varName,
                $value
            ));
            return;
        }

        if ($expected !== null) {
            $expectedBool = filter_var($expected, FILTER_VALIDATE_BOOLEAN);
            $actualBool = filter_var($value, FILTER_VALIDATE_BOOLEAN);

            if ($actualBool !== $expectedBool) {
                $result->addError(sprintf(
                    'Environment variable %s boolean value is %s, expected %s',
                    $varName,
                    $actualBool ? 'true' : 'false',
                    $expectedBool ? 'true' : 'false'
                ));
            }
        }
    }

    /**
     * Validate a numeric environment variable.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $value
     * @param array $expected
     * @return void
     */
    private function validateNumeric(ValidationResult $result, $varName, $value, $expected)
    {
        if (!is_numeric($value)) {
            $result->addError(sprintf(
                'Environment variable %s is not numeric, got: %s',
                $varName,
                $value
            ));
            return;
        }

        if (isset($expected['min']) && (float) $value < (float) $expected['min']) {
            $result->addError(sprintf(
                'Environment variable %s value %s is less than minimum %s',
                $varName,
                $value,
                $expected['min']
            ));
        }

        if (isset($expected['max']) && (float) $value > (float) $expected['max']) {
            $result->addError(sprintf(
                'Environment variable %s value %s is greater than maximum %s',
                $varName,
                $value,
                $expected['max']
            ));
        }
    }

    /**
     * Validate a minimum length/value constraint.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $value
     * @param mixed $min
     * @return void
     */
    private function validateMin(ValidationResult $result, $varName, $value, $min)
    {
        if (is_numeric($value)) {
            if ((float) $value < (float) $min) {
                $result->addError(sprintf(
                    'Environment variable %s value %s is less than %s',
                    $varName,
                    $value,
                    $min
                ));
            }
        } elseif (is_string($value)) {
            if (strlen($value) < (int) $min) {
                $result->addError(sprintf(
                    'Environment variable %s length %d is less than %d',
                    $varName,
                    strlen($value),
                    $min
                ));
            }
        }
    }

    /**
     * Validate a maximum length/value constraint.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $value
     * @param mixed $max
     * @return void
     */
    private function validateMax(ValidationResult $result, $varName, $value, $max)
    {
        if (is_numeric($value)) {
            if ((float) $value > (float) $max) {
                $result->addError(sprintf(
                    'Environment variable %s value %s is greater than %s',
                    $varName,
                    $value,
                    $max
                ));
            }
        } elseif (is_string($value)) {
            if (strlen($value) > (int) $max) {
                $result->addError(sprintf(
                    'Environment variable %s length %d is greater than %d',
                    $varName,
                    strlen($value),
                    $max
                ));
            }
        }
    }

    /**
     * Validate a value against a regular expression.
     *
     * @param ValidationResult $result
     * @param string $varName
     * @param mixed $value
     * @param string $pattern
     * @return void
     */
    private function validateRegex(ValidationResult $result, $varName, $value, $pattern)
    {
        $match = preg_match($pattern, (string) $value);

        if ($match === false) {
            $result->addError(sprintf(
                'Invalid regex pattern for environment variable %s: %s',
                $varName,
                $pattern
            ));
        } elseif ($match === 0) {
            $result->addError(sprintf(
                'Environment variable %s does not match pattern: %s',
                $varName,
                $pattern
            ));
        }
    }
}
