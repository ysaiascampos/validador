<?php

namespace Vendor\Validator\Adapters;

use Vendor\Validator\Contracts\JsonLoaderInterface;
use Vendor\Validator\Exceptions\ValidationException;

class JsonLoader implements JsonLoaderInterface
{
    /**
     * Load and parse a JSON file.
     *
     * @param string $path
     * @return array
     */
    public function load($path)
    {
        if (!file_exists($path)) {
            throw ValidationException::invalidJson(
                sprintf('File not found: %s', $path)
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw ValidationException::invalidJson(
                sprintf('Unable to read file: %s', $path)
            );
        }

        return $this->parse($contents);
    }

    /**
     * Load and parse a JSON string.
     *
     * @param string $json
     * @return array
     */
    public function parse($json)
    {
        if (!is_string($json) || empty($json)) {
            throw ValidationException::invalidJson('Empty or non-string input');
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::invalidJson(
                sprintf('JSON parse error: %s (code: %d)', json_last_error_msg(), json_last_error())
            );
        }

        if (!is_array($data)) {
            throw ValidationException::invalidJson('JSON must decode to an array or object');
        }

        return $data;
    }
}
