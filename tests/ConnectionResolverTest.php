<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Adapters\ConnectionResolver;

class ConnectionResolverTest extends TestCase
{
    public function testImplementsInterface()
    {
        $resolver = new ConnectionResolver();
        $this->assertInstanceOf(
            'Vendor\\Validator\\Contracts\\ConnectionResolverInterface',
            $resolver
        );
    }

    public function testGetDefaultConnection()
    {
        if (!class_exists('Illuminate\Support\Facades\DB')) {
            return;
        }

        $resolver = new ConnectionResolver();
        $default = $resolver->getDefaultConnection();

        $this->assertInternalType('string', $default);
    }
}
