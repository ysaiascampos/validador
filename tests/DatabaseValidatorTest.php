<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Validators\DatabaseValidator;
use Vendor\Validator\Contracts\OracleRepositoryInterface;

class DatabaseValidatorTest extends TestCase
{
    public function testGetName()
    {
        $repository = $this->getMockOracleRepository();
        $validator = new DatabaseValidator($repository);

        $this->assertEquals('database', $validator->getName());
    }

    public function testValidateWithNoObjectsReturnsWarning()
    {
        $repository = $this->getMockOracleRepository();
        $validator = new DatabaseValidator($repository);

        $result = $validator->validate();

        $this->assertTrue($result->isSuccess());

        $found = false;
        foreach ($result->getWarnings() as $warning) {
            if (strpos($warning, 'No database objects configured') !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }

    public function testValidateSchemasExists()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('schemaExists')->willReturn(true);

        $validator = new DatabaseValidator($repository, $this->config(array(
            'checklist' => array(
                'schemas' => array('ASIGNACION'),
            ),
        )));

        $result = $validator->validate();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('exists', $result->getDetails()['schemas']['ASIGNACION']);
    }

    public function testValidateSchemasNotExists()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('schemaExists')->willReturn(false);

        $validator = new DatabaseValidator($repository, $this->config(array(
            'checklist' => array(
                'schemas' => array('MISSING_SCHEMA'),
            ),
        )));

        $result = $validator->validate();

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('not_exists', $result->getDetails()['schemas']['MISSING_SCHEMA']);
    }

    public function testValidateTables()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('tableExists')->willReturn(true);

        $validator = new DatabaseValidator($repository, $this->config(array(
            'checklist' => array(
                'tables' => array('ASIGNACION.TABLE1', 'ASIGNACION.TABLE2'),
            ),
        )));

        $result = $validator->validate();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('exists', $result->getDetails()['tables']['ASIGNACION.TABLE1']);
    }

    public function testValidateColumnsMissing()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('columnExists')->willReturn(false);

        $validator = new DatabaseValidator($repository, $this->config(array(
            'checklist' => array(
                'columns' => array('ASIGNACION.TABLE1.ID'),
            ),
        )));

        $result = $validator->validate();

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('not_exists', $result->getDetails()['columns']['ASIGNACION.TABLE1.ID']);
    }

    public function testValidateColumnTypeMismatch()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('getColumn')->willReturn($this->column(array(
            'data_type' => 'VARCHAR2',
            'data_length' => 50,
        )));

        $validator = new DatabaseValidator($repository, $this->config(array(
            'checklist' => array(
                'column_types' => array('ASIGNACION.TABLE1.ID' => 'NUMBER'),
            ),
        )));

        $result = $validator->validate();

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('mismatch', $result->getDetails()['column_types']['ASIGNACION.TABLE1.ID']);
    }

    public function testValidateColumnTypeMatches()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('getColumn')->willReturn($this->column(array(
            'data_type' => 'NUMBER',
            'data_length' => 22,
            'nullable' => 'N',
        )));

        $validator = new DatabaseValidator($repository, $this->config(array(
            'checklist' => array(
                'column_types' => array('ASIGNACION.TABLE1.ID' => 'NUMBER'),
                'column_lengths' => array('ASIGNACION.TABLE1.ID' => 22),
                'nullable' => array('ASIGNACION.TABLE1.ID' => false),
                'defaults' => array('ASIGNACION.TABLE1.ID' => null),
            ),
        )));

        $result = $validator->validate();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('ok', $result->getDetails()['column_types']['ASIGNACION.TABLE1.ID']);
    }

    public function testValidateFunctions()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('functionExists')->willReturn(true);

        $validator = new DatabaseValidator($repository, $this->config(array(
            'checklist' => array(
                'functions' => array('ASIGNACION.GET_EMPLOYEE'),
            ),
        )));

        $result = $validator->validate();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('exists', $result->getDetails()['functions']['ASIGNACION.GET_EMPLOYEE']);
    }

    public function testValidatePrimaryKeys()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('primaryKeyExists')->willReturn(false);

        $validator = new DatabaseValidator($repository, $this->config(array(
            'checklist' => array(
                'primary_keys' => array('ASIGNACION.TABLE1'),
            ),
        )));

        $result = $validator->validate();

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('not_exists', $result->getDetails()['primary_keys']['ASIGNACION.TABLE1']);
    }

    public function testValidateMultipleConnections()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('schemaExists')->willReturn(true);

        $validator = new DatabaseValidator($repository, array(
            'default_connection' => 'oracle',
            'connections' => array(
                'oracle' => 'ASIGNACION',
                'oracle_export' => 'ASIGNACION_EXPORT',
            ),
            'database' => array(
                'connections' => array('oracle', 'oracle_export'),
                'checklist' => array(
                    'schemas' => array('ASIGNACION', 'ASIGNACION_EXPORT'),
                ),
            ),
        ));

        $result = $validator->validate();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(array('oracle', 'oracle_export'), $result->getDetails()['connections']);
        $this->assertEquals('exists', $result->getDetails()['schemas']['ASIGNACION']);
        $this->assertEquals('exists', $result->getDetails()['schemas']['ASIGNACION_EXPORT']);
    }

    public function testValidateUnknownTypeWarns()
    {
        $repository = $this->getMockOracleRepository();

        $validator = new DatabaseValidator($repository, $this->config(array(
            'checklist' => array(
                'materials' => array('foo'),
            ),
        )));

        $result = $validator->validate();

        $this->assertTrue($result->isSuccess());

        $found = false;
        foreach ($result->getWarnings() as $warning) {
            if (strpos($warning, 'Unknown database validation type') !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }

    public function testOptionsAreIgnoredAndConfigIsUsed()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('tableExists')->willReturn(true);

        $validator = new DatabaseValidator($repository, $this->config(array(
            'checklist' => array(
                'tables' => array('ASIGNACION.CONFIGURADA'),
            ),
        )));

        // Anything sent as a parameter must be ignored.
        $result = $validator->validate(array(
            'connections' => array('oracle_export'),
            'database' => array(
                'tables' => array('ASIGNACION.ENVIADA_POR_PARAMETRO'),
            ),
        ));

        $tables = $result->getDetails()['tables'];

        $this->assertArrayHasKey('ASIGNACION.CONFIGURADA', $tables);
        $this->assertArrayNotHasKey('ASIGNACION.ENVIADA_POR_PARAMETRO', $tables);
    }

    public function testBuildsChecklistFromExpectedSchemas()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('schemaExists')->willReturn(true);
        $repository->method('tableExists')->willReturn(true);
        $repository->method('getColumn')->willReturn($this->column(array(
            'data_type' => 'VARCHAR2',
            'data_length' => 10,
            'nullable' => 'N',
        )));
        $repository->method('primaryKeyExists')->willReturn(true);
        $repository->method('indexExists')->willReturn(true);
        $repository->method('functionExists')->willReturn(true);

        $validator = new DatabaseValidator($repository, $this->config(array(
            'expected_schemas' => array(
                'ASIGNACION' => array(
                    'tables' => array(
                        'PERSONAS' => array(
                            'columns' => array(
                                'RUT' => array(
                                    'type' => 'VARCHAR2',
                                    'length' => 10,
                                    'nullable' => false,
                                ),
                            ),
                            'primary_keys' => true,
                            'indexes' => array('IDX_PERSONAS_RUT'),
                        ),
                        'GRUPO_FAMILIAR',
                    ),
                    'functions' => array('GET_PERSONA'),
                ),
            ),
        )));

        $result = $validator->validate();
        $details = $result->getDetails();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('exists', $details['schemas']['ASIGNACION']);
        $this->assertEquals('exists', $details['tables']['ASIGNACION.PERSONAS']);
        $this->assertEquals('exists', $details['tables']['ASIGNACION.GRUPO_FAMILIAR']);
        $this->assertEquals('ok', $details['column_types']['ASIGNACION.PERSONAS.RUT']);
        $this->assertEquals('ok', $details['column_lengths']['ASIGNACION.PERSONAS.RUT']);
        $this->assertEquals('ok', $details['nullable']['ASIGNACION.PERSONAS.RUT']);
        $this->assertEquals('exists', $details['primary_keys']['ASIGNACION.PERSONAS']);
        $this->assertEquals('exists', $details['indexes']['ASIGNACION.PERSONAS.IDX_PERSONAS_RUT']);
        $this->assertEquals('exists', $details['functions']['ASIGNACION.GET_PERSONA']);
    }

    public function testExpectedSchemasAcceptsPlainSchemaList()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('schemaExists')->willReturn(true);

        $validator = new DatabaseValidator($repository, $this->config(array(
            'expected_schemas' => array('ASIGNACION', 'ASIGNACION_EXPORT'),
        )));

        $result = $validator->validate();
        $details = $result->getDetails();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('exists', $details['schemas']['ASIGNACION']);
        $this->assertEquals('exists', $details['schemas']['ASIGNACION_EXPORT']);
    }

    public function testChecklistIsMergedWithExpectedSchemas()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('tableExists')->willReturn(true);

        $validator = new DatabaseValidator($repository, $this->config(array(
            'expected_schemas' => array(
                'ASIGNACION' => array(
                    'tables' => array('PERSONAS'),
                ),
            ),
            'checklist' => array(
                'tables' => array('ASIGNACION.GRUPO_FAMILIAR'),
            ),
        )));

        $result = $validator->validate();
        $tables = $result->getDetails()['tables'];

        $this->assertArrayHasKey('ASIGNACION.PERSONAS', $tables);
        $this->assertArrayHasKey('ASIGNACION.GRUPO_FAMILIAR', $tables);
    }

    /**
     * Build the validator config.
     *
     * @param array $database
     * @return array
     */
    private function config(array $database)
    {
        return array(
            'default_connection' => 'oracle',
            'database' => $database,
        );
    }

    /**
     * Build a column row as returned by the Oracle repository.
     *
     * @param array $overrides
     * @return array
     */
    private function column(array $overrides = array())
    {
        return array_merge(array(
            'column_name' => 'ID',
            'data_type' => 'NUMBER',
            'data_length' => 22,
            'data_precision' => 10,
            'data_scale' => 0,
            'nullable' => 'Y',
            'data_default' => null,
            'column_id' => 1,
            'char_length' => null,
            'char_used' => null,
        ), $overrides);
    }

    private function getMockOracleRepository()
    {
        $mock = $this->getMockBuilder(OracleRepositoryInterface::class)
            ->setMethods(array(
                'setConnection',
                'getConnection',
                'getSchemas',
                'getCurrentUser',
                'getTables',
                'getColumns',
                'getFunctions',
                'getProcedures',
                'getSequences',
                'getViews',
                'getTriggers',
                'getPackages',
                'getSynonyms',
                'getIndexes',
                'getPrimaryKeys',
                'getForeignKeys',
                'getConstraints',
                'schemaExists',
                'tableExists',
                'columnExists',
                'getColumn',
                'functionExists',
                'procedureExists',
                'sequenceExists',
                'viewExists',
                'triggerExists',
                'packageExists',
                'synonymExists',
                'indexExists',
                'primaryKeyExists',
                'foreignKeyExists',
                'constraintExists',
                'getDdl',
            ))
            ->getMock();

        return $mock;
    }
}
