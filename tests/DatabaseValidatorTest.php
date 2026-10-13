<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Validators\DatabaseValidator;
use Vendor\Validator\Contracts\OracleRepositoryInterface;
use Vendor\Validator\DTO\ValidationResult;

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
        $warnings = $result->getWarnings();
        $found = false;
        foreach ($warnings as $warning) {
            if (strpos($warning, 'No database objects') !== false) {
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

        $validator = new DatabaseValidator($repository);
        $result = $validator->validate(array(
            'database' => array(
                'schemas' => array('ASIGNACION'),
            ),
        ));

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('exists', $result->getDetails()['schemas']['ASIGNACION']);
    }

    public function testValidateSchemasNotExists()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('schemaExists')->willReturn(false);

        $validator = new DatabaseValidator($repository);
        $result = $validator->validate(array(
            'database' => array(
                'schemas' => array('MISSING_SCHEMA'),
            ),
        ));

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('not_exists', $result->getDetails()['schemas']['MISSING_SCHEMA']);
    }

    public function testValidateTables()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('tableExists')->willReturn(true);

        $validator = new DatabaseValidator($repository);
        $result = $validator->validate(array(
            'database' => array(
                'tables' => array('ASIGNACION.TABLE1', 'ASIGNACION.TABLE2'),
            ),
        ));

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('exists', $result->getDetails()['tables']['ASIGNACION.TABLE1']);
    }

    public function testValidateColumnsMissing()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('columnExists')->willReturn(false);

        $validator = new DatabaseValidator($repository);
        $result = $validator->validate(array(
            'database' => array(
                'columns' => array('ASIGNACION.TABLE1.ID'),
            ),
        ));

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('not_exists', $result->getDetails()['columns']['ASIGNACION.TABLE1.ID']);
    }

    public function testValidateColumnTypeMismatch()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('getColumn')->willReturn(array(
            'column_name' => 'ID',
            'data_type' => 'VARCHAR2',
            'data_length' => 50,
            'data_precision' => null,
            'data_scale' => null,
            'nullable' => 'Y',
            'data_default' => null,
            'column_id' => 1,
            'char_length' => 50,
            'char_used' => 'B',
        ));

        $validator = new DatabaseValidator($repository);
        $result = $validator->validate(array(
            'database' => array(
                'column_types' => array('ASIGNACION.TABLE1.ID' => 'NUMBER'),
            ),
        ));

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('mismatch', $result->getDetails()['column_types']['ASIGNACION.TABLE1.ID']);
    }

    public function testValidateColumnTypeMatches()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('getColumn')->willReturn(array(
            'column_name' => 'ID',
            'data_type' => 'NUMBER',
            'data_length' => 22,
            'data_precision' => 10,
            'data_scale' => 0,
            'nullable' => 'N',
            'data_default' => null,
            'column_id' => 1,
            'char_length' => null,
            'char_used' => null,
        ));

        $validator = new DatabaseValidator($repository);
        $result = $validator->validate(array(
            'database' => array(
                'column_types' => array('ASIGNACION.TABLE1.ID' => 'NUMBER'),
                'column_lengths' => array('ASIGNACION.TABLE1.ID' => 22),
                'nullable' => array('ASIGNACION.TABLE1.ID' => false),
                'defaults' => array('ASIGNACION.TABLE1.ID' => null),
            ),
        ));

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('ok', $result->getDetails()['column_types']['ASIGNACION.TABLE1.ID']);
    }

    public function testValidateFunctions()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('functionExists')->willReturn(true);

        $validator = new DatabaseValidator($repository);
        $result = $validator->validate(array(
            'database' => array(
                'functions' => array('ASIGNACION.GET_EMPLOYEE'),
            ),
        ));

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('exists', $result->getDetails()['functions']['ASIGNACION.GET_EMPLOYEE']);
    }

    public function testValidatePrimaryKeys()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('primaryKeyExists')->willReturn(false);

        $validator = new DatabaseValidator($repository);
        $result = $validator->validate(array(
            'database' => array(
                'primary_keys' => array('ASIGNACION.TABLE1'),
            ),
        ));

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('not_exists', $result->getDetails()['primary_keys']['ASIGNACION.TABLE1']);
    }

    public function testValidateMultipleConnections()
    {
        $repository = $this->getMockOracleRepository();
        $repository->method('schemaExists')->willReturn(true);

        $validator = new DatabaseValidator($repository);
        $result = $validator->validate(array(
            'connections' => array('oracle', 'oracle_export'),
            'database' => array(
                'schemas' => array('ASIGNACION'),
            ),
        ));

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(array('oracle', 'oracle_export'), $result->getDetails()['connections']);
    }

    public function testValidateUnknownTypeWarns()
    {
        $repository = $this->getMockOracleRepository();
        $validator = new DatabaseValidator($repository);

        $result = $validator->validate(array(
            'database' => array(
                'materials' => array('foo'),
            ),
        ));

        $this->assertTrue($result->isSuccess());
        $warnings = $result->getWarnings();
        $found = false;
        foreach ($warnings as $warning) {
            if (strpos($warning, 'Unknown database validation type') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
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
