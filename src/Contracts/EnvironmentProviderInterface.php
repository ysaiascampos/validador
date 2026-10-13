<?php

namespace Vendor\Validator\Contracts;

interface EnvironmentProviderInterface
{
    /**
     * Get an environment variable value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * Check if an environment variable exists.
     *
     * @param string $key
     * @return bool
     */
    public function has($key);

    /**
     * Get all environment variables.
     *
     * @return array
     */
    public function all();
}
