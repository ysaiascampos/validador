<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Adapters\EnvironmentProvider;

class EnvironmentProviderTest extends TestCase
{
    public function testGetExistingVariable()
    {
        $_ENV['TEST_EXISTING'] = 'some_value';
        $provider = new EnvironmentProvider();

        $this->assertEquals('some_value', $provider->get('TEST_EXISTING'));
    }

    public function testGetNonExistentVariable()
    {
        $provider = new EnvironmentProvider();

        $this->assertNull($provider->get('NON_EXISTENT_VAR_' . uniqid()));
    }

    public function testGetWithDefault()
    {
        $provider = new EnvironmentProvider();

        $this->assertEquals('default_value', $provider->get('NON_EXISTENT_VAR', 'default_value'));
    }

    public function testHasExistingVariable()
    {
        $_ENV['TEST_HAS'] = 'exists';
        $provider = new EnvironmentProvider();

        $this->assertTrue($provider->has('TEST_HAS'));
    }

    public function testHasNonExistentVariable()
    {
        $provider = new EnvironmentProvider();

        $this->assertFalse($provider->has('NON_EXISTENT_VAR_' . uniqid()));
    }

    public function testAll()
    {
        $provider = new EnvironmentProvider();
        $all = $provider->all();

        $this->assertInternalType('array', $all);
    }

    public function testEmptyValueIsReturned()
    {
        $_ENV['TEST_EMPTY'] = '';
        $provider = new EnvironmentProvider();

        $this->assertEquals('', $provider->get('TEST_EMPTY'));
    }
}
