<?php

namespace Vendor\Validator\Validators;

use Vendor\Validator\Contracts\ValidatorInterface;
use Vendor\Validator\Contracts\OracleRepositoryInterface;
use Vendor\Validator\DTO\ValidationResult;
use Vendor\Validator\Exceptions\ValidationException;

class DatabaseValidator implements ValidatorInterface
{
    /**
     * The Oracle repository instance.
     *
     * @var OracleRepositoryInterface
     */
    private $repository;

    /**
     * Validation configuration.
     *
     * @var array
     */
    private $config;

    /**
     * Cached current connected user (schema).
     *
     * @var string|null
     */
    private $currentUser;

    /**
     * Constructor.
     *
     * @param OracleRepositoryInterface $repository
     * @param array $config
     */
    public function __construct(OracleRepositoryInterface $repository, array $config = array())
    {
        $this->repository = $repository;
        $this->config = $config;
        $this->currentUser = null;
    }

    /**
     * Get the validator name.
     *
     * @return string
     */
    public function getName()
    {
        return 'database';
    }

    /**
     * Execute database validation against a checklist of object names.
     *
     * @param array $options
     * @return ValidationResult
     */
    public function validate(array $options = array())
    {
        $startTime = microtime(true);
        $result = new ValidationResult($this->getName());

        $connections = $this->resolveConnections($options);
        $checklist = $this->resolveChecklist($options);

        try {
            $assignment = $this->assignItemsToConnections($connections, $checklist);

            foreach ($assignment as $connectionName => $connectionChecklist) {
                $this->repository->setConnection($connectionName);
                $this->currentUser = null;
                $this->validateChecklist($result, $connectionChecklist);
            }

            $result->addDetail('connections', $connections);

            if (empty($checklist)) {
                $result->addWarning('No database objects provided for validation.');
            }

        } catch (ValidationException $e) {
            $result->addError($e->getMessage());
            $result->addDetail('exception_context', $e->getContext());
        } catch (\Exception $e) {
            $result->addError(sprintf('Unexpected database validation error: %s', $e->getMessage()));
        }

        $executionTime = (microtime(true) - $startTime) * 1000;
        $result->setExecutionTime($executionTime);

        return $result;
    }

    /**
     * Resolve the list of database connections to validate.
     *
     * @param array $options
     * @return array
     */
    private function resolveConnections(array $options)
    {
        if (isset($options['connections']) && is_array($options['connections']) && !empty($options['connections'])) {
            return array_values($options['connections']);
        }

        if (isset($options['connection']) && !empty($options['connection'])) {
            return array($options['connection']);
        }

        if (isset($options['database']['connections']) && is_array($options['database']['connections']) && !empty($options['database']['connections'])) {
            return array_values($options['database']['connections']);
        }

        $defaultConnection = isset($this->config['default_connection'])
            ? $this->config['default_connection']
            : 'oracle';

        return array($defaultConnection);
    }

    /**
     * Resolve the checklist of database objects to validate.
     *
     * @param array $options
     * @return array
     */
    private function resolveChecklist(array $options)
    {
        $checklist = array();

        $database = (isset($options['database']) && is_array($options['database']))
            ? $options['database']
            : $options;

        unset($database['connection'], $database['connections']);

        foreach ($database as $type => $items) {
            if (is_array($items) && !empty($items)) {
                $checklist[$type] = $items;
            }
        }

        return $checklist;
    }

    /**
     * Assign each checklist item to exactly one connection.
     *
     * An item is assigned to the connection whose configured schema matches
     * the item's schema prefix. Items without a schema prefix, or whose schema
     * is not mapped to any requested connection, are assigned to the first
     * requested connection.
     *
     * @param array $connections
     * @param array $checklist
     * @return array
     */
    private function assignItemsToConnections(array $connections, array $checklist)
    {
        $assignment = array();
        $fallback = isset($connections[0]) ? $connections[0] : null;

        if ($fallback === null) {
            return $assignment;
        }

        foreach ($checklist as $type => $items) {
            foreach ($items as $key => $item) {
                $name = is_string($item) ? $item : (string) $key;
                $target = $this->connectionForItem($type, $name, $connections, $fallback);

                if (!isset($assignment[$target])) {
                    $assignment[$target] = array();
                }

                if (!isset($assignment[$target][$type])) {
                    $assignment[$target][$type] = array();
                }

                $assignment[$target][$type][$key] = $item;
            }
        }

        return $assignment;
    }

    /**
     * Determine which connection should validate an item.
     *
     * @param string $type
     * @param string $name
     * @param array $connections
     * @param string $fallback
     * @return string
     */
    private function connectionForItem($type, $name, array $connections, $fallback)
    {
        $schema = $this->schemaFromItem($type, $name);

        if (!empty($schema)) {
            foreach ($connections as $connectionName) {
                $connectionSchema = $this->connectionSchema($connectionName);

                if (!empty($connectionSchema) && strtoupper($schema) === strtoupper($connectionSchema)) {
                    return $connectionName;
                }
            }
        }

        return $fallback;
    }

    /**
     * Extract the schema from a checklist item name.
     *
     * @param string $type
     * @param string $name
     * @return string|null
     */
    private function schemaFromItem($type, $name)
    {
        if ($type === 'schemas') {
            return $name;
        }

        $parts = explode('.', $name);

        if (count($parts) >= 2) {
            return $parts[0];
        }

        return null;
    }

    /**
     * Get the configured schema for a connection name.
     *
     * @param string $connectionName
     * @return string
     */
    private function connectionSchema($connectionName)
    {
        if (isset($this->config['connections']) && is_array($this->config['connections'])
            && isset($this->config['connections'][$connectionName])) {
            return $this->config['connections'][$connectionName];
        }

        return '';
    }

    /**
     * Record a status map, accumulating entries across connections.
     *
     * @param ValidationResult $result
     * @param string $type
     * @param array $status
     * @return void
     */
    private function recordStatus(ValidationResult $result, $type, array $status)
    {
        $details = $result->getDetails();
        $existing = (isset($details[$type]) && is_array($details[$type]))
            ? $details[$type]
            : array();

        $result->addDetail($type, array_merge($existing, $status));
    }

    /**
     * Dispatch each checklist type to its checker.
     *
     * @param ValidationResult $result
     * @param array $checklist
     * @return void
     */
    private function validateChecklist(ValidationResult $result, array $checklist)
    {
        foreach ($checklist as $type => $items) {
            switch ($type) {
                case 'schemas':
                    $this->checkSchemas($result, $items);
                    break;

                case 'tables':
                    $this->checkTables($result, $items);
                    break;

                case 'columns':
                    $this->checkColumns($result, $items);
                    break;

                case 'column_types':
                    $this->checkColumnTypes($result, $items);
                    break;

                case 'column_lengths':
                    $this->checkColumnLengths($result, $items);
                    break;

                case 'nullable':
                    $this->checkNullables($result, $items);
                    break;

                case 'defaults':
                    $this->checkDefaults($result, $items);
                    break;

                case 'functions':
                    $this->checkFunctions($result, $items);
                    break;

                case 'procedures':
                    $this->checkProcedures($result, $items);
                    break;

                case 'sequences':
                    $this->checkSequences($result, $items);
                    break;

                case 'views':
                    $this->checkViews($result, $items);
                    break;

                case 'triggers':
                    $this->checkTriggers($result, $items);
                    break;

                case 'packages':
                    $this->checkPackages($result, $items);
                    break;

                case 'synonyms':
                    $this->checkSynonyms($result, $items);
                    break;

                case 'indexes':
                    $this->checkIndexes($result, $items);
                    break;

                case 'primary_keys':
                    $this->checkPrimaryKeys($result, $items);
                    break;

                case 'foreign_keys':
                    $this->checkForeignKeys($result, $items);
                    break;

                case 'constraints':
                    $this->checkConstraints($result, $items);
                    break;

                default:
                    $result->addWarning(sprintf('Unknown database validation type: %s', $type));
                    break;
            }
        }
    }

    /**
     * Check that the given schemas exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkSchemas(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $exists = $this->repository->schemaExists($name);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Schema does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'schemas', $status);
    }

    /**
     * Check that the given tables exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkTables(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseTableName($name);
            $exists = $this->repository->tableExists($parts['schema'], $parts['table']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Table does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'tables', $status);
    }

    /**
     * Check that the given columns exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkColumns(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseColumnName($name);
            $exists = $this->repository->columnExists($parts['schema'], $parts['table'], $parts['column']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Column does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'columns', $status);
    }

    /**
     * Check that the given columns have the expected data type.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkColumnTypes(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name => $expectedType) {
            $parts = $this->parseColumnName($name);
            $column = $this->repository->getColumn($parts['schema'], $parts['table'], $parts['column']);

            if ($column === null) {
                $status[$name] = 'not_exists';
                $result->addError(sprintf('Column does not exist: %s', $name));
                continue;
            }

            $actual = strtoupper(trim($column['data_type']));
            $expected = strtoupper(trim($expectedType));
            $ok = ($actual === $expected);
            $status[$name] = $ok ? 'ok' : 'mismatch';

            if (!$ok) {
                $result->addError(sprintf('Column %s type is %s, expected %s', $name, $actual, $expected));
            }
        }
        $this->recordStatus($result, 'column_types', $status);
    }

    /**
     * Check that the given columns have the expected length.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkColumnLengths(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name => $expectedLength) {
            $parts = $this->parseColumnName($name);
            $column = $this->repository->getColumn($parts['schema'], $parts['table'], $parts['column']);

            if ($column === null) {
                $status[$name] = 'not_exists';
                $result->addError(sprintf('Column does not exist: %s', $name));
                continue;
            }

            $actual = (int) $column['data_length'];
            $expected = (int) $expectedLength;
            $ok = ($actual === $expected);
            $status[$name] = $ok ? 'ok' : 'mismatch';

            if (!$ok) {
                $result->addError(sprintf('Column %s length is %d, expected %d', $name, $actual, $expected));
            }
        }
        $this->recordStatus($result, 'column_lengths', $status);
    }

    /**
     * Check that the given columns have the expected nullability.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkNullables(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name => $expectedNullable) {
            $parts = $this->parseColumnName($name);
            $column = $this->repository->getColumn($parts['schema'], $parts['table'], $parts['column']);

            if ($column === null) {
                $status[$name] = 'not_exists';
                $result->addError(sprintf('Column does not exist: %s', $name));
                continue;
            }

            $actual = ($column['nullable'] === 'Y');
            $expected = (bool) $expectedNullable;
            $ok = ($actual === $expected);
            $status[$name] = $ok ? 'ok' : 'mismatch';

            if (!$ok) {
                $result->addError(sprintf(
                    'Column %s nullable is %s, expected %s',
                    $name,
                    $actual ? 'Y' : 'N',
                    $expected ? 'Y' : 'N'
                ));
            }
        }
        $this->recordStatus($result, 'nullable', $status);
    }

    /**
     * Check that the given columns have the expected default value.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkDefaults(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name => $expectedDefault) {
            $parts = $this->parseColumnName($name);
            $column = $this->repository->getColumn($parts['schema'], $parts['table'], $parts['column']);

            if ($column === null) {
                $status[$name] = 'not_exists';
                $result->addError(sprintf('Column does not exist: %s', $name));
                continue;
            }

            $actual = $column['data_default'] === null ? '' : trim($column['data_default']);
            $expected = is_null($expectedDefault) ? '' : trim($expectedDefault);
            $ok = (strtoupper($actual) === strtoupper($expected));
            $status[$name] = $ok ? 'ok' : 'mismatch';

            if (!$ok) {
                $result->addError(sprintf(
                    'Column %s default is \'%s\', expected \'%s\'',
                    $name,
                    $actual === '' ? 'NULL' : $actual,
                    $expected === '' ? 'NULL' : $expected
                ));
            }
        }
        $this->recordStatus($result, 'defaults', $status);
    }

    /**
     * Check that the given functions exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkFunctions(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseObjectName($name);
            $exists = $this->repository->functionExists($parts['schema'], $parts['object']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Function does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'functions', $status);
    }

    /**
     * Check that the given procedures exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkProcedures(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseObjectName($name);
            $exists = $this->repository->procedureExists($parts['schema'], $parts['object']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Procedure does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'procedures', $status);
    }

    /**
     * Check that the given sequences exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkSequences(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseObjectName($name);
            $exists = $this->repository->sequenceExists($parts['schema'], $parts['object']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Sequence does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'sequences', $status);
    }

    /**
     * Check that the given views exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkViews(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseObjectName($name);
            $exists = $this->repository->viewExists($parts['schema'], $parts['object']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('View does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'views', $status);
    }

    /**
     * Check that the given triggers exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkTriggers(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseObjectName($name);
            $exists = $this->repository->triggerExists($parts['schema'], $parts['object']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Trigger does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'triggers', $status);
    }

    /**
     * Check that the given packages exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkPackages(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseObjectName($name);
            $exists = $this->repository->packageExists($parts['schema'], $parts['object']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Package does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'packages', $status);
    }

    /**
     * Check that the given synonyms exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkSynonyms(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseObjectName($name);
            $exists = $this->repository->synonymExists($parts['schema'], $parts['object']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Synonym does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'synonyms', $status);
    }

    /**
     * Check that the given indexes exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkIndexes(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseIndexName($name);
            $exists = $this->repository->indexExists($parts['schema'], $parts['table'], $parts['index']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Index does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'indexes', $status);
    }

    /**
     * Check that the given tables have a primary key.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkPrimaryKeys(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseTableName($name);
            $exists = $this->repository->primaryKeyExists($parts['schema'], $parts['table']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Primary key does not exist on: %s', $name));
            }
        }
        $this->recordStatus($result, 'primary_keys', $status);
    }

    /**
     * Check that the given foreign keys exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkForeignKeys(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseIndexName($name);
            $exists = $this->repository->foreignKeyExists($parts['schema'], $parts['table'], $parts['index']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Foreign key does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'foreign_keys', $status);
    }

    /**
     * Check that the given constraints exist.
     *
     * @param ValidationResult $result
     * @param array $items
     * @return void
     */
    private function checkConstraints(ValidationResult $result, array $items)
    {
        $status = array();
        foreach ($items as $name) {
            $parts = $this->parseIndexName($name);
            $exists = $this->repository->constraintExists($parts['schema'], $parts['table'], $parts['index']);
            $status[$name] = $this->statusOf($exists);
            if (!$exists) {
                $result->addError(sprintf('Constraint does not exist: %s', $name));
            }
        }
        $this->recordStatus($result, 'constraints', $status);
    }

    /**
     * Parse a SCHEMA.OBJECT name into its parts.
     *
     * @param string $name
     * @return array
     */
    private function parseObjectName($name)
    {
        $parts = explode('.', $name);

        if (count($parts) === 2) {
            return array('schema' => $parts[0], 'object' => $parts[1]);
        }

        return array('schema' => $this->currentSchema(), 'object' => $parts[0]);
    }

    /**
     * Parse a SCHEMA.TABLE name into its parts.
     *
     * @param string $name
     * @return array
     */
    private function parseTableName($name)
    {
        $parts = explode('.', $name);

        if (count($parts) === 2) {
            return array('schema' => $parts[0], 'table' => $parts[1]);
        }

        return array('schema' => $this->currentSchema(), 'table' => $parts[0]);
    }

    /**
     * Parse a SCHEMA.TABLE.COLUMN name into its parts.
     *
     * @param string $name
     * @return array
     */
    private function parseColumnName($name)
    {
        $parts = explode('.', $name);

        if (count($parts) === 3) {
            return array('schema' => $parts[0], 'table' => $parts[1], 'column' => $parts[2]);
        }

        if (count($parts) === 2) {
            return array('schema' => $this->currentSchema(), 'table' => $parts[0], 'column' => $parts[1]);
        }

        return array('schema' => $this->currentSchema(), 'table' => $parts[0], 'column' => '');
    }

    /**
     * Parse a SCHEMA.TABLE.INDEX name into its parts.
     *
     * @param string $name
     * @return array
     */
    private function parseIndexName($name)
    {
        $parts = explode('.', $name);

        if (count($parts) === 3) {
            return array('schema' => $parts[0], 'table' => $parts[1], 'index' => $parts[2]);
        }

        if (count($parts) === 2) {
            return array('schema' => $this->currentSchema(), 'table' => $parts[0], 'index' => $parts[1]);
        }

        return array('schema' => $this->currentSchema(), 'table' => '', 'index' => $parts[0]);
    }

    /**
     * Get the current connected user (schema).
     *
     * @return string|null
     */
    private function currentSchema()
    {
        if ($this->currentUser === null) {
            $this->currentUser = $this->repository->getCurrentUser();
        }

        return $this->currentUser;
    }

    /**
     * Convert an existence boolean into a status label.
     *
     * @param bool $exists
     * @return string
     */
    private function statusOf($exists)
    {
        return $exists ? 'exists' : 'not_exists';
    }
}
