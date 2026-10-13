<?php

namespace Vendor\Validator\Adapters;

use Vendor\Validator\Contracts\EnvironmentProviderInterface;

class EnvironmentProvider implements EnvironmentProviderInterface
{
    /**
     * Get an environment variable value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $value = env($key);

        if (is_null($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * Check if an environment variable exists.
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return !is_null(env($key));
    }

    /**
     * Get all environment variables.
     *
     * @return array
     */
    public function all()
    {
        return $_ENV;
    }
}
