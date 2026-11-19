<?php

namespace Vendor\Validator\Services;

class ReportLinkSigner
{
    /**
     * Default lifetime of a signed download link, in seconds.
     */
    const DEFAULT_TTL = 300;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var int
     */
    private $ttl;

    /**
     * Constructor.
     *
     * @param string $secret
     * @param int|null $ttl
     */
    public function __construct($secret, $ttl = null)
    {
        $this->secret = (string) $secret;
        $this->ttl = ($ttl === null || (int) $ttl <= 0) ? self::DEFAULT_TTL : (int) $ttl;
    }

    /**
     * Whether signing is possible (a secret is configured).
     *
     * @return bool
     */
    public function enabled()
    {
        return $this->secret !== '';
    }

    /**
     * Expiration timestamp for a link created now.
     *
     * @param int|null $now
     * @return int
     */
    public function expiresAt($now = null)
    {
        $now = $now === null ? time() : (int) $now;

        return $now + $this->ttl;
    }

    /**
     * Signature for a download link.
     *
     * @param int|string $expires
     * @return string
     */
    public function sign($expires)
    {
        return hash_hmac('sha256', 'report.pdf|' . (int) $expires, $this->secret);
    }

    /**
     * Validate an expiration timestamp and its signature.
     *
     * @param int|string $expires
     * @param string $signature
     * @param int|null $now
     * @return bool
     */
    public function verify($expires, $signature, $now = null)
    {
        if (!$this->enabled() || !is_scalar($expires) || !is_string($signature)) {
            return false;
        }

        if (!preg_match('/^\d+$/', (string) $expires) || $signature === '') {
            return false;
        }

        $now = $now === null ? time() : (int) $now;

        if ((int) $expires < $now) {
            return false;
        }

        return hash_equals($this->sign($expires), $signature);
    }
}
