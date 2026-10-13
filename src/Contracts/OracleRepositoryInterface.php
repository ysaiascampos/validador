<?php

namespace Vendor\Validator\Contracts;

interface OracleRepositoryInterface
{
    /**
     * Set the database connection name.
     *
     * @param string $connection
     * @return void
     */
    public function setConnection($connection);

    /**
     * Get the current connection name.
     *
     * @return string
     */
    public function getConnection();

    /**
     * Retrieve all schemas (users) from the database.
     *
     * @return array
     */
    public function getSchemas();

    /**
     * Get the current connected user (schema) for the active connection.
     *
     * @return string|null
     */
    public function getCurrentUser();

    /**
     * Retrieve all tables for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getTables($schema);

    /**
     * Retrieve all columns for a given table and schema.
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    public function getColumns($schema, $table);

    /**
     * Retrieve all functions for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getFunctions($schema);

    /**
     * Retrieve all procedures for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getProcedures($schema);

    /**
     * Retrieve all sequences for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getSequences($schema);

    /**
     * Retrieve all views for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getViews($schema);

    /**
     * Retrieve all triggers for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getTriggers($schema);

    /**
     * Retrieve all packages for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getPackages($schema);

    /**
     * Retrieve all synonyms for a given schema.
     *
     * @param string $schema
     * @return array
     */
    public function getSynonyms($schema);

    /**
     * Retrieve all indexes for a given table and schema.
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    public function getIndexes($schema, $table);

    /**
     * Retrieve primary keys for a given table and schema.
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    public function getPrimaryKeys($schema, $table);

    /**
     * Retrieve foreign keys for a given table and schema.
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    public function getForeignKeys($schema, $table);

    /**
     * Retrieve all constraints for a given table and schema.
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    public function getConstraints($schema, $table);

    /**
     * Check if a column exists in a table.
     *
     * @param string $schema
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function columnExists($schema, $table, $column);

    /**
     * Get a single column definition.
     *
     * @param string $schema
     * @param string $table
     * @param string $column
     * @return array|null
     */
    public function getColumn($schema, $table, $column);

    /**
     * Check if a function exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function functionExists($schema, $name);

    /**
     * Check if a procedure exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function procedureExists($schema, $name);

    /**
     * Check if a sequence exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function sequenceExists($schema, $name);

    /**
     * Check if a view exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function viewExists($schema, $name);

    /**
     * Check if a trigger exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function triggerExists($schema, $name);

    /**
     * Check if a package exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function packageExists($schema, $name);

    /**
     * Check if a synonym exists in a schema.
     *
     * @param string $schema
     * @param string $name
     * @return bool
     */
    public function synonymExists($schema, $name);

    /**
     * Check if an index exists on a table.
     *
     * @param string $schema
     * @param string $table
     * @param string $name
     * @return bool
     */
    public function indexExists($schema, $table, $name);

    /**
     * Check if a table has a primary key.
     *
     * @param string $schema
     * @param string $table
     * @return bool
     */
    public function primaryKeyExists($schema, $table);

    /**
     * Check if a foreign key exists on a table.
     *
     * @param string $schema
     * @param string $table
     * @param string $name
     * @return bool
     */
    public function foreignKeyExists($schema, $table, $name);

    /**
     * Check if a constraint exists on a table.
     *
     * @param string $schema
     * @param string $table
     * @param string $name
     * @return bool
     */
    public function constraintExists($schema, $table, $name);
}
