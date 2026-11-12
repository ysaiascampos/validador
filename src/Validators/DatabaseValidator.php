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
     * Execute database validation using the configuration file only.
     *
     * Database validation no longer accepts a checklist, schema list or
     * connection list as parameters: everything must be declared in
     * config/validator.php under the "database" key.
     *
     * @param array $options Ignored. Kept for ValidatorInterface compatibility.
     * @return ValidationResult
     */
    public function validate(array $options = array())
    {
        $startTime = microtime(true);
        $result = new ValidationResult($this->getName());

        $connections = $this->resolveConnections();
        $checklist = $this->resolveChecklist();

        try {
            $assignment = $this->assignItemsToConnections($connections, $checklist);

            foreach ($assignment as $connectionName => $connectionChecklist) {
                $this->repository->setConnection($connectionName);
                $this->currentUser = null;
                $this->validateChecklist($result, $connectionChecklist);
            }

            $result->addDetail('connections', $connections);

            if (empty($checklist)) {
                $result->addWarning(
                    'No database objects configured for validation. '
                    . 'Declare them in config/validator.php under database.expected_schemas or database.checklist.'
                );
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
     * Get the database section of the validation config.
     *
     * @return array
     */
    private function databaseConfig()
    {
        return (isset($this->config['database']) && is_array($this->config['database']))
            ? $this->config['database']
            : array();
    }

    /**
     * Resolve the list of database connections to validate from the config.
     *
     * @return array
     */
    private function resolveConnections()
    {
        $database = $this->databaseConfig();

        if (isset($database['connections']) && is_array($database['connections']) && !empty($database['connections'])) {
            return array_values($database['connections']);
        }

        $defaultConnection = isset($this->config['default_connection'])
            ? $this->config['default_connection']
            : 'oracle';

        return array($defaultConnection);
    }

    /**
     * Resolve the checklist of database objects to validate from the config.
     *
     * Objects can be declared in two ways (both are merged):
     *  - database.expected_schemas: nested by schema, with relative names.
     *  - database.checklist: flat type => items map, using the same format
     *    that was previously accepted from the request payload.
     *
     * @return array
     */
    private function resolveChecklist()
    {
        $database = $this->databaseConfig();
        $checklist = array();

        if (isset($database['expected_schemas']) && is_array($database['expected_schemas']) && !empty($database['expected_schemas'])) {
            $checklist = $this->buildChecklistFromExpectedSchemas($database['expected_schemas']);
        }

        if (isset($database['checklist']) && is_array($database['checklist']) && !empty($database['checklist'])) {
            $checklist = $this->mergeChecklists($checklist, $database['checklist']);
        }

        return $checklist;
    }

    /**
     * Build the flat checklist from the nested expected_schemas definition.
     *
     * @param array $schemas
     * @return array
     */
    private function buildChecklistFromExpectedSchemas(array $schemas)
    {
        $checklist = array();

        foreach ($schemas as $key => $definition) {
            // Support a plain list of schema names: array('ASIGNACION', 'EXPORT').
            if (is_int($key)) {
                if (is_string($definition) && trim($definition) !== '') {
                    $this->appendItems($checklist, 'schemas', array(trim($definition)));
                }
                continue;
            }

            $schema = trim((string) $key);

            if ($schema === '') {
                continue;
            }

            $this->appendItems($checklist, 'schemas', array($schema));

            if (!is_array($definition)) {
                continue;
            }

            foreach ($definition as $type => $items) {
                switch ($type) {
                    case 'tables':
                        $this->addTablesFromDefinition($checklist, $schema, $items);
                        break;

                    case 'columns':
                        $this->addSchemaColumns($checklist, $schema, $items);
                        break;

                    case 'column_types':
                    case 'column_lengths':
                    case 'nullable':
                    case 'defaults':
                        $this->addMapType($checklist, $type, $schema, $items, 3);
                        break;

                    case 'primary_keys':
                        $this->addPrimaryKeysFromDefinition($checklist, $schema, $items);
                        break;

                    case 'indexes':
                    case 'foreign_keys':
                    case 'constraints':
                        $this->addListType($checklist, $type, $schema, $items, 3);
                        break;

                    case 'functions':
                    case 'procedures':
                    case 'sequences':
                    case 'views':
                    case 'triggers':
                    case 'packages':
                    case 'synonyms':
                        $this->addListType($checklist, $type, $schema, $items, 2);
                        break;

                    default:
                        // Unknown keys are ignored so extra metadata is allowed.
                        break;
                }
            }
        }

        return $checklist;
    }

    /**
     * Add the tables (and their nested objects) of one schema.
     *
     * Supported forms:
     *   'tables' => array('PERSONAS', 'GRUPO_FAMILIAR')
     *   'tables' => array('PERSONAS' => array(
     *       'columns' => array('RUT' => array('type' => 'VARCHAR2', 'length' => 10, 'nullable' => false)),
     *       'indexes' => array('IDX_PERSONAS_RUT'),
     *       'primary_keys' => true,
     *       'foreign_keys' => array('FK_PERSONAS_GRUPO'),
     *       'constraints' => array('CK_PERSONAS_ESTADO'),
     *   ))
     *
     * @param array $checklist
     * @param string $schema
     * @param mixed $tables
     * @return void
     */
    private function addTablesFromDefinition(array &$checklist, $schema, $tables)
    {
        if (!is_array($tables)) {
            $this->appendItems($checklist, 'tables', array($this->qualify($schema, $tables, 2)));
            return;
        }

        foreach ($tables as $key => $value) {
            if (is_int($key)) {
                $this->appendItems($checklist, 'tables', array($this->qualify($schema, $value, 2)));
                continue;
            }

            $table = $this->qualify($schema, $key, 2);
            $this->appendItems($checklist, 'tables', array($table));

            if (!is_array($value)) {
                continue;
            }

            if (isset($value['columns'])) {
                $this->addColumnsFromDefinition($checklist, $table, $value['columns']);
            }

            if (isset($value['indexes'])) {
                $this->addListType($checklist, 'indexes', $table, $value['indexes'], 3);
            }

            if (isset($value['foreign_keys'])) {
                $this->addListType($checklist, 'foreign_keys', $table, $value['foreign_keys'], 3);
            }

            if (isset($value['constraints'])) {
                $this->addListType($checklist, 'constraints', $table, $value['constraints'], 3);
            }

            if (array_key_exists('primary_keys', $value)) {
                $this->addPrimaryKeysFromDefinition($checklist, $schema, $value['primary_keys'], $table);
            }
        }
    }

    /**
     * Add the columns of one schema.
     *
     * Supported forms:
     *   'columns' => array('PERSONAS.RUT', 'PERSONAS.NOMBRE')
     *   'columns' => array('PERSONAS.RUT' => array('type' => 'VARCHAR2'))
     *   'columns' => array('PERSONAS' => array('RUT' => 'VARCHAR2'))
     *
     * @param array $checklist
     * @param string $schema
     * @param mixed $columns
     * @return void
     */
    private function addSchemaColumns(array &$checklist, $schema, $columns)
    {
        if (!is_array($columns)) {
            $this->appendItems($checklist, 'columns', array($this->qualify($schema, $columns, 3)));
            return;
        }

        if ($this->isListArray($columns)) {
            $this->addListType($checklist, 'columns', $schema, $columns, 3);
            return;
        }

        foreach ($columns as $key => $definition) {
            $parts = explode('.', (string) $key);

            if (count($parts) >= 2) {
                // TABLE.COLUMN => definition
                $this->applyColumnDefinition(
                    $checklist,
                    $this->qualify($schema, $key, 3),
                    $definition
                );
                continue;
            }

            // TABLE => array(COLUMN => definition)
            if (is_array($definition)) {
                $this->addColumnsFromDefinition(
                    $checklist,
                    $this->qualify($schema, $key, 2),
                    $definition
                );
            }
        }
    }

    /**
     * Add the columns of one table, including type/length/nullable/default.
     *
     * Supported forms:
     *   'columns' => array('RUT', 'NOMBRE')
     *   'columns' => array('RUT' => 'VARCHAR2')
     *   'columns' => array('RUT' => array('type' => 'VARCHAR2', 'length' => 10, 'nullable' => false, 'default' => null))
     *
     * @param array $checklist
     * @param string $table Fully qualified SCHEMA.TABLE.
     * @param mixed $columns
     * @return void
     */
    private function addColumnsFromDefinition(array &$checklist, $table, $columns)
    {
        if (!is_array($columns)) {
            $this->appendItems($checklist, 'columns', array($this->qualify($table, $columns, 3)));
            return;
        }

        foreach ($columns as $name => $definition) {
            if (is_int($name)) {
                $this->appendItems($checklist, 'columns', array($this->qualify($table, $definition, 3)));
                continue;
            }

            $this->applyColumnDefinition(
                $checklist,
                $this->qualify($table, $name, 3),
                $definition
            );
        }
    }

    /**
     * Apply a single column definition (existence, type, length, nullability,
     * default) to the checklist.
     *
     * @param array $checklist
     * @param string $column Fully qualified SCHEMA.TABLE.COLUMN.
     * @param mixed $definition
     * @return void
     */
    private function applyColumnDefinition(array &$checklist, $column, $definition)
    {
        if (!is_array($definition)) {
            // Shorthand: 'COLUMN' => 'VARCHAR2'.
            $this->appendMap($checklist, 'column_types', $column, $definition);
            return;
        }

        $checked = false;

        if (array_key_exists('type', $definition)) {
            $this->appendMap($checklist, 'column_types', $column, $definition['type']);
            $checked = true;
        }

        if (array_key_exists('length', $definition)) {
            $this->appendMap($checklist, 'column_lengths', $column, $definition['length']);
            $checked = true;
        }

        if (array_key_exists('nullable', $definition)) {
            $this->appendMap($checklist, 'nullable', $column, $definition['nullable']);
            $checked = true;
        }

        if (array_key_exists('default', $definition)) {
            $this->appendMap($checklist, 'defaults', $column, $definition['default']);
            $checked = true;
        }

        if (!$checked) {
            $this->appendItems($checklist, 'columns', array($column));
        }
    }

    /**
     * Whether an array is a plain list (sequential numeric keys).
     *
     * @param array $array
     * @return bool
     */
    private function isListArray(array $array)
    {
        $index = 0;

        foreach (array_keys($array) as $key) {
            if ($key !== $index) {
                return false;
            }

            $index++;
        }

        return true;
    }

    /**
     * Add list-style items for a type, qualifying relative names with the prefix.
     *
     * @param array $checklist
     * @param string $type
     * @param string $prefix
     * @param mixed $items
     * @param int $requiredParts Total name parts expected (1 schema, 2 object, 3 column/index).
     * @return void
     */
    private function addListType(array &$checklist, $type, $prefix, $items, $requiredParts)
    {
        if (!is_array($items)) {
            $items = array($items);
        }

        foreach ($items as $key => $value) {
            if (!is_int($key) && is_array($value)) {
                // Map form: 'TABLE' => array('INDEX1', 'INDEX2').
                $objectPrefix = $this->qualify($prefix, $key, 2);

                foreach ($value as $item) {
                    $this->appendItems($checklist, $type, array($this->qualify($objectPrefix, $item, $requiredParts)));
                }
                continue;
            }

            $this->appendItems($checklist, $type, array($this->qualify($prefix, $value, $requiredParts)));
        }
    }

    /**
     * Add map-style items (column => expected value), qualifying column names.
     *
     * @param array $checklist
     * @param string $type
     * @param string $prefix
     * @param mixed $items
     * @param int $requiredParts
     * @return void
     */
    private function addMapType(array &$checklist, $type, $prefix, $items, $requiredParts)
    {
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $name => $expected) {
            $this->appendMap($checklist, $type, $this->qualify($prefix, $name, $requiredParts), $expected);
        }
    }

    /**
     * Add primary key checks.
     *
     * Supported forms:
     *   'primary_keys' => true
     *   'primary_keys' => array('TABLE1', 'TABLE2')
     *   'primary_keys' => array('TABLE1' => true)
     *
     * Inside a table block the table itself is the target, so any truthy value
     * (true, a constraint name, a non-empty list) enables the check for it.
     *
     * @param array $checklist
     * @param string $schema
     * @param mixed $items
     * @param string|null $table Table already qualified (nested table block).
     * @return void
     */
    private function addPrimaryKeysFromDefinition(array &$checklist, $schema, $items, $table = null)
    {
        if ($table !== null) {
            if ($items === false || $items === null || $items === array() || $items === '') {
                return;
            }

            $this->appendItems($checklist, 'primary_keys', array($table));
            return;
        }

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $key => $value) {
            if (is_int($key)) {
                $this->appendItems($checklist, 'primary_keys', array($this->qualify($schema, $value, 2)));
                continue;
            }

            if ($value === true || $value === 1) {
                $this->appendItems($checklist, 'primary_keys', array($this->qualify($schema, $key, 2)));
            }
        }
    }

    /**
     * Qualify a relative object name with the given prefix.
     *
     * @param string $prefix
     * @param mixed $name
     * @param int $requiredParts
     * @return string
     */
    private function qualify($prefix, $name, $requiredParts)
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        if (count(explode('.', $name)) >= $requiredParts) {
            return $name;
        }

        return rtrim((string) $prefix, '.') . '.' . $name;
    }

    /**
     * Append items to a list-style checklist entry, without duplicates.
     *
     * @param array $checklist
     * @param string $type
     * @param array $items
     * @return void
     */
    private function appendItems(array &$checklist, $type, array $items)
    {
        if (!isset($checklist[$type])) {
            $checklist[$type] = array();
        }

        foreach ($items as $item) {
            $item = trim((string) $item);

            if ($item !== '' && !in_array($item, $checklist[$type], true)) {
                $checklist[$type][] = $item;
            }
        }
    }

    /**
     * Set a map-style checklist entry.
     *
     * @param array $checklist
     * @param string $type
     * @param string $name
     * @param mixed $value
     * @return void
     */
    private function appendMap(array &$checklist, $type, $name, $value)
    {
        if ($name === '') {
            return;
        }

        if (!isset($checklist[$type])) {
            $checklist[$type] = array();
        }

        $checklist[$type][$name] = $value;
    }

    /**
     * Merge two checklists (explicit checklist overrides expected_schemas).
     *
     * @param array $base
     * @param array $overrides
     * @return array
     */
    private function mergeChecklists(array $base, array $overrides)
    {
        foreach ($overrides as $type => $items) {
            if (!is_array($items) || empty($items)) {
                continue;
            }

            if (!isset($base[$type])) {
                $base[$type] = $items;
                continue;
            }

            if ($this->isMapType($type)) {
                $base[$type] = array_merge($base[$type], $items);
                continue;
            }

            $base[$type] = array_values(array_unique(array_merge($base[$type], array_values($items))));
        }

        return $base;
    }

    /**
     * Whether a checklist type is keyed by object name (map) instead of a list.
     *
     * @param string $type
     * @return bool
     */
    private function isMapType($type)
    {
        return in_array($type, array('column_types', 'column_lengths', 'nullable', 'defaults'), true);
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
                // Map-style types (column_types, nullable...) are keyed by the
                // object name; list-style types store the name as the value.
                $name = is_int($key) ? (string) $item : (string) $key;
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
