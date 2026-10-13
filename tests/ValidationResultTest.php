<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\DTO\ValidationResult;

class ValidationResultTest extends TestCase
{
    public function testConstructorInitializesWithSuccess()
    {
        $result = new ValidationResult('test');
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('test', $result->getValidatorName());
        $this->assertEmpty($result->getErrors());
        $this->assertEmpty($result->getWarnings());
        $this->assertEmpty($result->getDetails());
    }

    public function testAddErrorSetsSuccessToFalse()
    {
        $result = new ValidationResult();
        $result->addError('Something went wrong');

        $this->assertFalse($result->isSuccess());
        $this->assertCount(1, $result->getErrors());
        $this->assertEquals('Something went wrong', $result->getErrors()[0]);
    }

    public function testAddWarningDoesNotAffectSuccess()
    {
        $result = new ValidationResult();
        $result->addWarning('This is a warning');

        $this->assertTrue($result->isSuccess());
        $this->assertCount(1, $result->getWarnings());
        $this->assertEquals('This is a warning', $result->getWarnings()[0]);
    }

    public function testMergeCombinesResults()
    {
        $result1 = new ValidationResult('validator1');
        $result1->addError('Error 1');
        $result1->addWarning('Warning 1');
        $result1->addDetail('key1', 'value1');

        $result2 = new ValidationResult('validator2');
        $result2->addError('Error 2');
        $result2->addDetail('key2', 'value2');

        $result1->merge($result2);

        $this->assertFalse($result1->isSuccess());
        $this->assertCount(2, $result1->getErrors());
        $this->assertCount(1, $result1->getWarnings());
        $this->assertCount(2, $result1->getDetails());
    }

    public function testMergeSuccessfulResultDoesNotChangeSuccess()
    {
        $result1 = new ValidationResult('v1');
        $result1->addWarning('Warning only');

        $result2 = new ValidationResult('v2');

        $result1->merge($result2);

        $this->assertTrue($result1->isSuccess());
    }

    public function testSetErrorsSetsSuccessFalse()
    {
        $result = new ValidationResult();
        $result->setErrors(array('Error A', 'Error B'));

        $this->assertFalse($result->isSuccess());
        $this->assertCount(2, $result->getErrors());
    }

    public function testToArrayReturnsExpectedStructure()
    {
        $result = new ValidationResult('my_validator');
        $result->addError('Test error');
        $result->addWarning('Test warning');
        $result->addDetail('info', 'test');

        $array = $result->toArray();

        $this->assertEquals('my_validator', $array['validator']);
        $this->assertFalse($array['success']);
        $this->assertContains('Test error', $array['errors']);
        $this->assertContains('Test warning', $array['warnings']);
        $this->assertEquals('test', $array['details']['info']);
        $this->assertArrayHasKey('execution_time', $array);
    }

    public function testExecutionTime()
    {
        $result = new ValidationResult();
        $result->setExecutionTime(123.45);

        $this->assertEquals(123.45, $result->getExecutionTime());
    }
}
