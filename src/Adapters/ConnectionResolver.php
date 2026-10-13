<?php

namespace Vendor\Validator\Adapters;

use Vendor\Validator\Contracts\ConnectionResolverInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class ConnectionResolver implements ConnectionResolverInterface
{
    /**
     * Cached connection instances.
     *
     * @var array
     */
    private $connections = array();

    /**
     * Get a database connection instance by name.
     *
     * @param string|null $name
     * @return ConnectionInterface
     */
    public function connection($name = null)
    {
        $name = is_null($name) ? $this->getDefaultConnection() : $name;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = DB::connection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Get the default connection name from the Laravel configuration.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return DB::getDefaultConnection();
    }
}
