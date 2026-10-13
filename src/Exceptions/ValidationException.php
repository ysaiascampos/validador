<?php

namespace Vendor\Validator\Exceptions;

class ValidationException extends \RuntimeException
{
    /**
     * The validation context data.
     *
     * @var array
     */
    private $context;

    /**
     * Constructor.
     *
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     * @param array $context
     */
    public function __construct($message = '', $code = 0, \Exception $previous = null, array $context = array())
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the validation context.
     *
     * @return array
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Create an exception for an invalid connection name.
     *
     * @param string $connection
     * @return self
     */
    public static function invalidConnection($connection)
    {
        return new static(
            sprintf('Invalid database connection: %s', $connection),
            1001,
            null,
            array('connection' => $connection)
        );
    }

    /**
     * Create an exception for a missing schema.
     *
     * @param string $schema
     * @return self
     */
    public static function missingSchema($schema)
    {
        return new static(
            sprintf('Database schema not found: %s', $schema),
            1002,
            null,
            array('schema' => $schema)
        );
    }

    /**
     * Create an exception for a missing table.
     *
     * @param string $schema
     * @param string $table
     * @return self
     */
    public static function missingTable($schema, $table)
    {
        return new static(
            sprintf('Table not found: %s.%s', $schema, $table),
            1003,
            null,
            array('schema' => $schema, 'table' => $table)
        );
    }

    /**
     * Create an exception for an invalid API token.
     *
     * @return self
     */
    public static function invalidApiToken()
    {
        return new static(
            'Invalid API token provided.',
            401,
            null,
            array()
        );
    }

    /**
     * Create an exception for an invalid JSON payload.
     *
     * @param string $error
     * @return self
     */
    public static function invalidJson($error)
    {
        return new static(
            sprintf('Invalid JSON payload: %s', $error),
            1004,
            null,
            array('parse_error' => $error)
        );
    }
}
