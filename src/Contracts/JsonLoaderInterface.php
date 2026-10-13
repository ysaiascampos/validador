<?php

namespace Vendor\Validator\Contracts;

interface JsonLoaderInterface
{
    /**
     * Load and parse a JSON file.
     *
     * @param string $path
     * @return array
     */
    public function load($path);

    /**
     * Load and parse a JSON string.
     *
     * @param string $json
     * @return array
     */
    public function parse($json);
}
