<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Oracle\OracleRepository;
use Vendor\Validator\Contracts\ConnectionResolverInterface;

class OracleRepositoryTest extends TestCase
{
    public function testSetAndGetConnection()
    {
        $resolver = $this->getMockResolver();
        $repository = new OracleRepository($resolver, 'oracle');

        $this->assertEquals('oracle', $repository->getConnection());

        $repository->setConnection('oracle2');
        $this->assertEquals('oracle2', $repository->getConnection());
    }

    public function testGetSchemasDelegatesToResolver()
    {
        $resolver = $this->getMockResolver();
        $repository = new OracleRepository($resolver, 'oracle');

        $schemas = $repository->getSchemas();
        $this->assertInternalType('array', $schemas);
    }

    public function testGetTablesDelegatesToResolver()
    {
        $resolver = $this->getMockResolver();
        $repository = new OracleRepository($resolver, 'oracle');

        $tables = $repository->getTables('SCHEMA1');
        $this->assertInternalType('array', $tables);
    }

    public function testGetColumnsDelegatesToResolver()
    {
        $resolver = $this->getMockResolver();
        $repository = new OracleRepository($resolver, 'oracle');

        $columns = $repository->getColumns('SCHEMA1', 'TABLE1');
        $this->assertInternalType('array', $columns);
    }

    public function testSchemaExistsReturnsBoolean()
    {
        $resolver = $this->getMockResolver();
        $repository = new OracleRepository($resolver, 'oracle');

        $exists = $repository->schemaExists('SCHEMA1');
        $this->assertInternalType('bool', $exists);
    }

    public function testTableExistsReturnsBoolean()
    {
        $resolver = $this->getMockResolver();
        $repository = new OracleRepository($resolver, 'oracle');

        $exists = $repository->tableExists('SCHEMA1', 'TABLE1');
        $this->assertInternalType('bool', $exists);
    }

    private function getMockResolver()
    {
        $mock = $this->getMockBuilder(ConnectionResolverInterface::class)
            ->setMethods(array('connection', 'getDefaultConnection'))
            ->getMock();

        $connMock = $this->getMockBuilder('stdClass')
            ->setMethods(array('select', 'statement'))
            ->getMock();

        $connMock->method('select')->willReturn(array());
        $connMock->method('statement')->willReturn(true);

        $mock->method('connection')->willReturn($connMock);

        return $mock;
    }
}
