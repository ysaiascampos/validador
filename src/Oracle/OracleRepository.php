<?php

namespace Vendor\Validator\Oracle;

use Vendor\Validator\Contracts\OracleRepositoryInterface;
use Vendor\Validator\Contracts\ConnectionResolverInterface;
use Vendor\Validator\Exceptions\ValidationException;

class OracleRepository implements OracleRepositoryInterface
{
    /**
     * The connection resolver instance.
     *
     * @var ConnectionResolverInterface
     */
    private $resolver;

    /**
     * The database connection name.
     *
     * @var string
     */
    private $connection;

    /**
     * Constructor.
     *
     * @param ConnectionResolverInterface $resolver
     * @param string $connection
     */
    public function __construct(ConnectionResolverInterface $resolver, $connection = 'oracle')
    {
        $this->resolver = $resolver;
        $this->connection = $connection;
    }

    /**
     * Set the database connection name.
     *
     * @param string $connection
     * @return void
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the current connection name.
     *
     * @return string
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Execute a raw SQL query against the Oracle connection.
     *
     * @param string $sql
     * @param array $bindings
     * @return array
     */
    private function executeQuery($sql, array $bindings = array())
    {
        try {
            $connection = $this->resolver->connection($this->connection);
            $results = $connection->select($sql, $bindings);
            return $this->castResultsToArray($results);
        } catch (\Exception $e) {
            throw new ValidationException(
                sprintf('Oracle query failed: %s', $e->getMessage()),
                2001,
                $e,
                array('connection' => $this->connection, 'sql' => $sql)
            );
        }
    }

    /**
     * Execute a raw SQL statement (non-select).
     *
     * @param string $sql
     * @param array $bindings
     * @return bool
     */
    private function executeStatement($sql, array $bindings = array())
    {
        try {
            $connection = $this->resolver->connection($this->connection);
            return $connection->statement($sql, $bindings);
        } catch (\Exception $e) {
            throw new ValidationException(
                sprintf('Oracle statement failed: %s', $e->getMessage()),
                2002,
                $e,
                array('connection' => $this->connection, 'sql' => $sql)
            );
        }
    }

    /**
     * Cast stdClass results to arrays with normalized lowercase keys.
     *
     * @param array $results
     * @return array
     */
    private function castResultsToArray(array $results)
    {
        $output = array();
        foreach ($results as $row) {
            if (is_object($row)) {
                $output[] = array_change_key_case((array) $row, CASE_LOWER);
            } elseif (is_array($row)) {
                $output[] = array_change_key_case($row, CASE_LOWER);
            } else {
                $output[] = $row;
            }
        }
        return $output;
    }

    /**
     * Retrieve all schemas (users) from the database.
     *
     * @return array
     */
    public function getSchemas()
    {
        return $this->executeQuery(
            'SELECT username FROM all_users ORDER BY username'
        );
    }

    /**
     * Get the current connected user (schema) for the active connection.
     *
     * @return string|null
     */
    public function getCurrentUser()
    {
        $results = $this->executeQuery('SELECT USER AS current_user FROM dual');

        if (!empty($results) && isset($results[0]['current_user'])) {
            return $results[0]['current_user'];
        }

        return null;
    }

    /**
     * Retrieve all tables for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getTables($schema)
    {
        return $this->executeQuery(
            'SELECT table_name, owner, tablespace_name, status FROM all_tables WHERE owner = :owner ORDER BY table_name',
            array('owner' => strtoupper($schema))
        );
    }

    /**
     * Retrieve all columns for a given table and schema.
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    public function getColumns($schema, $table)
    {
        return $this->executeQuery(
            'SELECT column_name, data_type, data_length, data_precision, data_scale, nullable, data_default, column_id, char_length, char_used FROM all_tab_columns WHERE owner = :owner AND table_name = :p_table ORDER BY column_id',
            array(
                'owner' => strtoupper($schema),
                'p_table' => strtoupper($table),
            )
        );
    }

    /**
     * Retrieve all functions for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getFunctions($schema)
    {
        return $this->executeQuery(
            'SELECT object_name, object_id, created, last_ddl_time, status FROM all_objects WHERE owner = :owner AND object_type = \'FUNCTION\' ORDER BY object_name',
            array('owner' => strtoupper($schema))
        );
    }

    /**
     * Retrieve all procedures for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getProcedures($schema)
    {
        return $this->executeQuery(
            'SELECT object_name, object_id, created, last_ddl_time, status FROM all_objects WHERE owner = :owner AND object_type = \'PROCEDURE\' ORDER BY object_name',
            array('owner' => strtoupper($schema))
        );
    }

    /**
     * Retrieve all sequences for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getSequences($schema)
    {
        return $this->executeQuery(
            'SELECT sequence_name, min_value, max_value, increment_by, last_number, cycle_flag, order_flag, cache_size FROM all_sequences WHERE sequence_owner = :owner ORDER BY sequence_name',
            array('owner' => strtoupper($schema))
        );
    }

    /**
     * Retrieve all views for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getViews($schema)
    {
        return $this->executeQuery(
            'SELECT view_name, text_length, text, status FROM all_views WHERE owner = :owner ORDER BY view_name',
            array('owner' => strtoupper($schema))
        );
    }

    /**
     * Retrieve all triggers for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getTriggers($schema)
    {
        return $this->executeQuery(
            'SELECT trigger_name, trigger_type, triggering_event, table_name, status, description FROM all_triggers WHERE owner = :owner ORDER BY trigger_name',
            array('owner' => strtoupper($schema))
        );
    }

    /**
     * Retrieve all packages for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getPackages($schema)
    {
        return $this->executeQuery(
            'SELECT object_name, object_id, created, last_ddl_time, status FROM all_objects WHERE owner = :owner AND object_type = \'PACKAGE\' ORDER BY object_name',
            array('owner' => strtoupper($schema))
        );
    }

    /**
     * Retrieve all synonyms for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getSynonyms($schema)
    {
        return $this->executeQuery(
            'SELECT synonym_name, table_owner, table_name, db_link FROM all_synonyms WHERE owner = :owner ORDER BY synonym_name',
            array('owner' => strtoupper($schema))
        );
    }

    /**
     * Retrieve all indexes for a given table and schema.
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    public function getIndexes($schema, $table)
    {
        return $this->executeQuery(
            'SELECT index_name, index_type, uniqueness, status, tablespace_name, visibility FROM all_indexes WHERE owner = :owner AND table_name = :p_table ORDER BY index_name',
            array(
                'owner' => strtoupper($schema),
                'p_table' => strtoupper($table),
            )
        );
    }

    /**
     * Retrieve primary keys for a given table and schema.
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    public function getPrimaryKeys($schema, $table)
    {
        return $this->executeQuery(
            'SELECT acc.constraint_name, acc.column_name, acc.position FROM all_cons_columns acc INNER JOIN all_constraints ac ON ac.constraint_name = acc.constraint_name AND ac.owner = acc.owner WHERE acc.owner = :owner AND acc.table_name = :p_table AND ac.constraint_type = \'P\' ORDER BY acc.position',
            array(
                'owner' => strtoupper($schema),
                'p_table' => strtoupper($table),
            )
        );
    }

    /**
     * Retrieve foreign keys for a given table and schema.
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    public function getForeignKeys($schema, $table)
    {
        return $this->executeQuery(
            'SELECT ac.constraint_name, acc.column_name, ac.r_owner AS references_owner, ac.r_constraint_name AS references_constraint, ac.delete_rule, ac.status FROM all_constraints ac INNER JOIN all_cons_columns acc ON acc.constraint_name = ac.constraint_name AND acc.owner = ac.owner WHERE ac.owner = :owner AND ac.table_name = :p_table AND ac.constraint_type = \'R\' ORDER BY ac.constraint_name, acc.position',
            array(
                'owner' => strtoupper($schema),
                'p_table' => strtoupper($table),
            )
        );
    }

    /**
     * Retrieve all constraints for a given table and schema.
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    public function getConstraints($schema, $table)
    {
        return $this->executeQuery(
            'SELECT constraint_name, constraint_type, search_condition, r_owner, r_constraint_name, delete_rule, status, deferrable, deferred, validated FROM all_constraints WHERE owner = :owner AND table_name = :p_table ORDER BY constraint_name',
            array(
                'owner' => strtoupper($schema),
                'p_table' => strtoupper($table),
            )
        );
    }

    /**
     * Check if a schema exists.
     *
     * @param string $schema
     * @return bool
     */
    public function schemaExists($schema)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_users WHERE username = :username',
            array('username' => strtoupper($schema))
        );
    }

    /**
     * Check if a table exists in a schema.
     *
     * @param string $schema
     * @param string $table
     * @return bool
     */
    public function tableExists($schema, $table)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_tables WHERE owner = :owner AND table_name = :p_table',
            array(
                'owner' => strtoupper($schema),
                'p_table' => strtoupper($table),
            )
        );
    }

    /**
     * Check if a column exists in a table.
     *
     * @param string $schema
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function columnExists($schema, $table, $column)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_tab_columns WHERE owner = :owner AND table_name = :p_table AND column_name = :p_column',
            array(
                'owner'  => strtoupper($schema),
                'p_table'  => strtoupper($table),
                'p_column' => strtoupper($column),
            )
        );
    }

    /**
     * Get a single column definition.
     *
     * @param string $schema
     * @param string $table
     * @param string $column
     * @return array|null
     */
    public function getColumn($schema, $table, $column)
    {
        $results = $this->executeQuery(
            'SELECT column_name, data_type, data_length, data_precision, data_scale, nullable, data_default, column_id, char_length, char_used FROM all_tab_columns WHERE owner = :owner AND table_name = :p_table AND column_name = :p_column',
            array(
                'owner'  => strtoupper($schema),
                'p_table'  => strtoupper($table),
                'p_column' => strtoupper($column),
            )
        );

        return isset($results[0]) ? $results[0] : null;
    }

    /**
     * Check if a function exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function functionExists($schema, $name)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_objects WHERE owner = :owner AND object_type = \'FUNCTION\' AND object_name = :name',
            array(
                'owner' => strtoupper($schema),
                'name'  => strtoupper($name),
            )
        );
    }

    /**
     * Check if a procedure exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function procedureExists($schema, $name)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_objects WHERE owner = :owner AND object_type = \'PROCEDURE\' AND object_name = :name',
            array(
                'owner' => strtoupper($schema),
                'name'  => strtoupper($name),
            )
        );
    }

    /**
     * Check if a sequence exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function sequenceExists($schema, $name)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_sequences WHERE sequence_owner = :owner AND sequence_name = :name',
            array(
                'owner' => strtoupper($schema),
                'name'  => strtoupper($name),
            )
        );
    }

    /**
     * Check if a view exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function viewExists($schema, $name)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_views WHERE owner = :owner AND view_name = :name',
            array(
                'owner' => strtoupper($schema),
                'name'  => strtoupper($name),
            )
        );
    }

    /**
     * Check if a trigger exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function triggerExists($schema, $name)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_triggers WHERE owner = :owner AND trigger_name = :name',
            array(
                'owner' => strtoupper($schema),
                'name'  => strtoupper($name),
            )
        );
    }

    /**
     * Check if a package exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function packageExists($schema, $name)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_objects WHERE owner = :owner AND object_type = \'PACKAGE\' AND object_name = :name',
            array(
                'owner' => strtoupper($schema),
                'name'  => strtoupper($name),
            )
        );
    }

    /**
     * Check if a synonym exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function synonymExists($schema, $name)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_synonyms WHERE owner = :owner AND synonym_name = :name',
            array(
                'owner' => strtoupper($schema),
                'name'  => strtoupper($name),
            )
        );
    }

    /**
     * Check if an index exists on a table.
     *
     * @param string $schema
     * @param string $table
     * @param string $name
     * @return bool
     */
    public function indexExists($schema, $table, $name)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_indexes WHERE owner = :owner AND table_name = :p_table AND index_name = :name',
            array(
                'owner' => strtoupper($schema),
                'p_table' => strtoupper($table),
                'name'  => strtoupper($name),
            )
        );
    }

    /**
     * Check if a table has a primary key.
     *
     * @param string $schema
     * @param string $table
     * @return bool
     */
    public function primaryKeyExists($schema, $table)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_constraints WHERE owner = :owner AND table_name = :p_table AND constraint_type = \'P\'',
            array(
                'owner' => strtoupper($schema),
                'p_table' => strtoupper($table),
            )
        );
    }

    /**
     * Check if a foreign key exists on a table.
     *
     * @param string $schema
     * @param string $table
     * @param string $name
     * @return bool
     */
    public function foreignKeyExists($schema, $table, $name)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_constraints WHERE owner = :owner AND table_name = :p_table AND constraint_type = \'R\' AND constraint_name = :name',
            array(
                'owner' => strtoupper($schema),
                'p_table' => strtoupper($table),
                'name'  => strtoupper($name),
            )
        );
    }

    /**
     * Check if a constraint exists on a table.
     *
     * @param string $schema
     * @param string $table
     * @param string $name
     * @return bool
     */
    public function constraintExists($schema, $table, $name)
    {
        return $this->countExists(
            'SELECT COUNT(*) AS cnt FROM all_constraints WHERE owner = :owner AND table_name = :p_table AND constraint_name = :name',
            array(
                'owner' => strtoupper($schema),
                'p_table' => strtoupper($table),
                'name'  => strtoupper($name),
            )
        );
    }

    /**
     * Run a COUNT(*) query and return whether the count is greater than zero.
     *
     * @param string $sql
     * @param array $bindings
     * @return bool
     */
    private function countExists($sql, array $bindings = array())
    {
        $results = $this->executeQuery($sql, $bindings);

        return (!empty($results) && isset($results[0]['cnt']) && (int) $results[0]['cnt'] > 0);
    }

    /**
     * Retrieve the DDL for a given object.
     *
     * @param string $objectType
     * @param string $schema
     * @param string $objectName
     * @return string|null
     */
    public function getDdl($objectType, $schema, $objectName)
    {
        $sql = 'SELECT dbms_metadata.get_ddl(:object_type, :object_name, :schema) AS ddl FROM dual';

        try {
            $connection = $this->resolver->connection($this->connection);
            $results = $connection->select($sql, array(
                'object_type' => strtoupper($objectType),
                'object_name' => strtoupper($objectName),
                'schema'      => strtoupper($schema),
            ));

            $results = $this->castResultsToArray($results);

            if (!empty($results) && isset($results[0]['ddl'])) {
                return $results[0]['ddl'];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
