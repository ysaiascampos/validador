<?php

namespace Vendor\Validator\Contracts;

use Illuminate\Database\ConnectionInterface;

interface ConnectionResolverInterface
{
    /**
     * Get a database connection instance by name.
     *
     * @param string $name
     * @return ConnectionInterface
     */
    public function connection($name = null);

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection();
}
