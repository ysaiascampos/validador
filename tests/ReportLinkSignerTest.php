<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Services\ReportLinkSigner;

class ReportLinkSignerTest extends TestCase
{
    public function testSignedLinkVerifies()
    {
        $signer = new ReportLinkSigner('clave-secreta', 300);
        $expires = $signer->expiresAt(1000);

        $this->assertSame(1300, $expires);
        $this->assertTrue($signer->verify($expires, $signer->sign($expires), 1000));
    }

    public function testExpiredLinkIsRejected()
    {
        $signer = new ReportLinkSigner('clave-secreta', 300);
        $expires = $signer->expiresAt(1000);

        $this->assertFalse($signer->verify($expires, $signer->sign($expires), 1400));
    }

    public function testTamperedSignatureIsRejected()
    {
        $signer = new ReportLinkSigner('clave-secreta', 300);
        $expires = $signer->expiresAt(1000);
        $signature = $signer->sign($expires);

        $this->assertFalse($signer->verify($expires, strrev($signature), 1000));
        $this->assertFalse($signer->verify($expires + 600, $signature, 1000));
    }

    public function testAnotherSecretIsRejected()
    {
        $signer = new ReportLinkSigner('clave-secreta', 300);
        $other = new ReportLinkSigner('otra-clave', 300);
        $expires = $signer->expiresAt(1000);

        $this->assertFalse($other->verify($expires, $signer->sign($expires), 1000));
    }

    public function testWithoutSecretSigningIsDisabled()
    {
        $signer = new ReportLinkSigner('', 300);

        $this->assertFalse($signer->enabled());
        $this->assertFalse($signer->verify(1300, 'cualquier-firma', 1000));
    }

    public function testInvalidExpirationIsRejected()
    {
        $signer = new ReportLinkSigner('clave-secreta', 300);

        $this->assertFalse($signer->verify('manana', 'firma', 1000));
        $this->assertFalse($signer->verify(1300, '', 1000));
        $this->assertFalse($signer->verify(array(1300), 'firma', 1000));
    }

    public function testDefaultTtlIsUsedWhenInvalid()
    {
        $signer = new ReportLinkSigner('clave-secreta', 0);

        $this->assertSame(1000 + ReportLinkSigner::DEFAULT_TTL, $signer->expiresAt(1000));
    }
}
