<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Laravel42CompatibilityTest extends TestCase
{
    /**
     * Helpers globales que Laravel 4.2 no define (llegaron en 5.0). Si el
     * paquete los usa, la aplicacion 4.2 falla con "Call to undefined
     * function".
     *
     * "env" queda fuera a proposito: el ServiceProvider define un fallback
     * cuando el framework no lo trae.
     *
     * @return array
     */
    private function helpersMissingInLaravel42()
    {
        return array(
            'auth',
            'back',
            'bcrypt',
            'cache',
            'config',
            'csrf_field',
            'decrypt',
            'dispatch',
            'elixir',
            'encrypt',
            'factory',
            'method_field',
            'policy',
            'request',
            'session',
            'view',
        );
    }

    public function testSourceDoesNotCallHelpersMissingInLaravel42()
    {
        $violations = array();

        foreach ($this->sourceFiles() as $file) {
            foreach ($this->helperCalls(file_get_contents($file)) as $call) {
                $violations[] = $this->relativePath($file) . ': ' . $call;
            }
        }

        $this->assertSame(
            array(),
            $violations,
            "Laravel 4.2 no define estos helpers globales:\n" . implode("\n", $violations)
        );
    }

    /**
     * @return array
     */
    private function sourceFiles()
    {
        $files = array();
        $directory = __DIR__ . '/../src';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Find calls to the listed helpers in a PHP source string.
     *
     * @param string $source
     * @return array
     */
    private function helperCalls($source)
    {
        $helpers = $this->helpersMissingInLaravel42();
        $tokens = token_get_all($source);
        $total = count($tokens);
        $calls = array();

        for ($index = 0; $index < $total; $index++) {
            $token = $tokens[$index];

            if (!is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            $name = strtolower($token[1]);

            if (!in_array($name, $helpers, true)) {
                continue;
            }

            if ($this->nextToken($tokens, $index, $total) !== '(') {
                continue;
            }

            $previous = $this->previousToken($tokens, $index);

            if ($previous === '->' || $previous === '::' || $previous === 'function') {
                continue;
            }

            $calls[] = $name . '() en la linea ' . $token[2];
        }

        return $calls;
    }

    /**
     * @param array $tokens
     * @param int $index
     * @return string|null
     */
    private function previousToken(array $tokens, $index)
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }

            if (is_array($tokens[$i])) {
                if ($tokens[$i][0] === T_OBJECT_OPERATOR) {
                    return '->';
                }

                if ($tokens[$i][0] === T_DOUBLE_COLON) {
                    return '::';
                }

                if ($tokens[$i][0] === T_FUNCTION) {
                    return 'function';
                }

                return $tokens[$i][1];
            }

            return $tokens[$i];
        }

        return null;
    }

    /**
     * @param array $tokens
     * @param int $index
     * @param int $total
     * @return string|null
     */
    private function nextToken(array $tokens, $index, $total)
    {
        for ($i = $index + 1; $i < $total; $i++) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }

            return is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
        }

        return null;
    }

    /**
     * @param string $file
     * @return string
     */
    private function relativePath($file)
    {
        return str_replace('\\', '/', substr($file, strlen(__DIR__ . '/..') + 1));
    }
}
