<?php

namespace Vendor\Validator\Adapters;

use Vendor\Validator\Contracts\EnvironmentProviderInterface;

class EnvFileProvider implements EnvironmentProviderInterface
{
    /**
     * Path to the .env file.
     *
     * @var string
     */
    private $path;

    /**
     * Parsed key/value pairs from the .env file.
     *
     * @var array
     */
    private $values;

    /**
     * Constructor.
     *
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->values = array();

        if (file_exists($path)) {
            $this->values = $this->parseFile($path);
        }

        $this->values = $this->resolveInterpolations($this->values);
    }

    /**
     * Get the .env file path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get a variable value from the .env file.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    /**
     * Check if a variable exists in the .env file.
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * Get all variables defined in the .env file.
     *
     * @return array
     */
    public function all()
    {
        return $this->values;
    }

    /**
     * Parse the file at the given path into a key/value array.
     *
     * PHP files (.env.php / .env.local.php, el formato de Laravel 4.2) se
     * evalúan y se aplanan con puntos, igual que hace el framework. Los
     * archivos dotenv (.env) se parsean por líneas KEY=VALUE.
     *
     * @param string $path
     * @return array
     */
    private function parseFile($path)
    {
        if (strtolower(substr($path, -4)) === '.php') {
            $fileValues = require $path;
            return is_array($fileValues) ? $this->flatten($fileValues) : array();
        }

        return $this->parse((string) file_get_contents($path));
    }

    /**
     * Flatten a nested array using dot notation (misma semántica que
     * array_dot de Laravel 4.2).
     *
     * @param array $array
     * @param string $prepend
     * @return array
     */
    private function flatten(array $array, $prepend = '')
    {
        $results = array();

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $results = array_merge($results, $this->flatten($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    /**
     * Parse dotenv-style content into an associative array.
     *
     * @param string $content
     * @return array
     */
    private function parse($content)
    {
        $values = array();

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', $content);

        foreach ($lines as $line) {
            $line = ltrim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (preg_match('/^export\s+/i', $line)) {
                $line = preg_replace('/^export\s+/i', '', $line);
            }

            $pos = strpos($line, '=');

            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));

            if ($key === '') {
                continue;
            }

            $values[$key] = $this->unquote(trim(substr($line, $pos + 1)));
        }

        return $values;
    }

    /**
     * Strip surrounding single or double quotes from a value.
     *
     * @param string $value
     * @return string
     */
    private function unquote($value)
    {
        $length = strlen($value);

        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, $length - 2);
            }
        }

        return $value;
    }

    /**
     * Resolve ${VAR}, ${VAR:-default} and $VAR references inside values using
     * the variables defined in the same file (falling back to the process env).
     *
     * @param array $raw
     * @return array
     */
    private function resolveInterpolations(array $raw)
    {
        $resolved = array();

        foreach (array_keys($raw) as $key) {
            $value = $raw[$key];
            $resolved[$key] = is_string($value) ? $this->resolveValue($raw, $key, array()) : $value;
        }

        return $resolved;
    }

    /**
     * Resolve the value of a single variable, expanding references.
     *
     * @param array $raw
     * @param string $key
     * @param array $stack
     * @return string
     */
    private function resolveValue(array $raw, $key, array $stack)
    {
        if (isset($stack[$key])) {
            return isset($raw[$key]) ? $raw[$key] : '';
        }

        $value = isset($raw[$key]) ? $raw[$key] : '';

        if (!is_string($value)) {
            return $value;
        }

        $stack[$key] = true;

        return preg_replace_callback(
            '/\$\{([A-Za-z0-9_]+)(:-([^}]*))?\}|\$([A-Za-z0-9_]+)/',
            function ($matches) use ($raw, $stack) {
                $name = !empty($matches[1]) ? $matches[1] : (isset($matches[4]) ? $matches[4] : '');
                $default = isset($matches[3]) ? $matches[3] : null;

                if ($name !== '' && array_key_exists($name, $raw)) {
                    return $this->resolveValue($raw, $name, $stack);
                }

                if ($name !== '' && function_exists('getenv')) {
                    $envValue = getenv($name);
                    if ($envValue !== false) {
                        return $envValue;
                    }
                }

                if ($default !== null) {
                    return $default;
                }

                return '';
            },
            $value
        );
    }
}
