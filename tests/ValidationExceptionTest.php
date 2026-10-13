<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Exceptions\ValidationException;

class ValidationExceptionTest extends TestCase
{
    public function testInvalidConnection()
    {
        $e = ValidationException::invalidConnection('oracle_custom');

        $this->assertEquals(1001, $e->getCode());
        $this->assertArrayHasKey('connection', $e->getContext());
        $this->assertEquals('oracle_custom', $e->getContext()['connection']);
    }

    public function testMissingSchema()
    {
        $e = ValidationException::missingSchema('MY_SCHEMA');

        $this->assertEquals(1002, $e->getCode());
        $this->assertArrayHasKey('schema', $e->getContext());
    }

    public function testMissingTable()
    {
        $e = ValidationException::missingTable('SCHEMA', 'TABLE');

        $this->assertEquals(1003, $e->getCode());
        $this->assertArrayHasKey('schema', $e->getContext());
        $this->assertArrayHasKey('table', $e->getContext());
    }

    public function testInvalidApiToken()
    {
        $e = ValidationException::invalidApiToken();

        $this->assertEquals(401, $e->getCode());
    }

    public function testInvalidJson()
    {
        $e = ValidationException::invalidJson('Parse error at line 1');

        $this->assertEquals(1004, $e->getCode());
        $this->assertArrayHasKey('parse_error', $e->getContext());
    }

    public function testContextIsMutable()
    {
        $e = new ValidationException('Test', 0, null, array('key' => 'value'));
        $context = $e->getContext();

        $this->assertEquals('value', $context['key']);
    }

    public function testMessageIsPreserved()
    {
        $message = 'Custom validation error message';
        $e = new ValidationException($message, 500);

        $this->assertEquals($message, $e->getMessage());
        $this->assertEquals(500, $e->getCode());
    }
}
