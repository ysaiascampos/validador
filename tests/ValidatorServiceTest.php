<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Services\ValidatorService;
use Vendor\Validator\Contracts\ValidatorInterface;
use Vendor\Validator\DTO\ValidationResult;

class ValidatorServiceTest extends TestCase
{
    public function testNoValidatorsReturnsWarning()
    {
        $service = new ValidatorService(
            'Vendor\\Validator\\Tests\\Stubs\\',
            __DIR__ . '/Stubs'
        );

        $result = $service->validate();

        $this->assertTrue($result->isSuccess());
        $this->assertCount(0, $result->getErrors());
    }

    public function testRegisterValidator()
    {
        $service = new ValidatorService();
        $mockValidator = $this->getMockValidator('mock_test');

        $service->registerValidator($mockValidator);

        $this->assertCount(1, $service->getValidators());
        $this->assertNotNull($service->getValidator('mock_test'));
    }

    public function testValidateAllValidators()
    {
        $service = new ValidatorService();

        $mock1 = $this->getMockValidator('validator_a');
        $mock2 = $this->getMockValidator('validator_b');

        $service->registerValidators(array($mock1, $mock2));

        $result = $service->validate();

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('validator_a', $result->getDetails());
        $this->assertArrayHasKey('validator_b', $result->getDetails());
    }

    public function testValidateSpecific()
    {
        $service = new ValidatorService();

        $mock = $this->getMockValidator('specific_one');
        $service->registerValidator($mock);

        $result = $service->validateSpecific('specific_one');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('specific_one', $result->getValidatorName());
    }

    public function testValidateSpecificNotFound()
    {
        $service = new ValidatorService();

        $result = $service->validateSpecific('nonexistent');

        $this->assertFalse($result->isSuccess());
    }

    public function testValidatorExceptionIsHandled()
    {
        $service = new ValidatorService();

        $mock = $this->getMockBuilder(ValidatorInterface::class)
            ->setMethods(array('validate', 'getName'))
            ->getMock();

        $mock->method('getName')->willReturn('failing');
        $mock->method('validate')->willThrowException(new \RuntimeException('Test exception'));

        $service->registerValidator($mock);

        $result = $service->validate();

        $details = $result->getDetails();
        $this->assertArrayHasKey('failing', $details);
        $this->assertFalse($result->isSuccess());
    }

    public function testDisableSpecificValidator()
    {
        $service = new ValidatorService();

        $mock = $this->getMockValidator('database');
        $service->registerValidator($mock);

        $result = $service->validate(array('database' => false));

        $this->assertTrue($result->isSuccess());
    }

    public function testDatabaseOptionsAreNotForwardedToTheValidator()
    {
        $service = new ValidatorService();

        $mock = $this->getMockBuilder(ValidatorInterface::class)
            ->setMethods(array('validate', 'getName'))
            ->getMock();
        $mock->method('getName')->willReturn('database');
        $mock->expects($this->once())
            ->method('validate')
            ->with($this->equalTo(array()))
            ->willReturn(new ValidationResult('database'));

        $service->registerValidator($mock);

        // Database objects sent as parameters must never reach the validator.
        $result = $service->validate(array(
            'database' => array(
                'tables' => array('ASIGNACION.PERSONAS'),
            ),
        ));

        $this->assertTrue($result->isSuccess());
    }

    private function getMockValidator($name)
    {
        $mock = $this->getMockBuilder(ValidatorInterface::class)
            ->setMethods(array('validate', 'getName'))
            ->getMock();

        $mock->method('getName')->willReturn($name);

        $mockResult = new ValidationResult($name);
        $mock->method('validate')->willReturn($mockResult);

        return $mock;
    }
}
