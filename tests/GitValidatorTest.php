<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Validators\GitValidator;

class GitValidatorTest extends TestCase
{
    public function testNotInitialized()
    {
        $tmpDir = sys_get_temp_dir() . '/validator_test_' . uniqid();
        mkdir($tmpDir, 0777, true);

        $validator = new GitValidator($tmpDir);
        $result = $validator->validate();

        $this->assertFalse($result->isSuccess());

        $errors = $result->getErrors();
        $found = false;
        foreach ($errors as $error) {
            if (strpos($error, 'not initialized') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        $this->cleanupDir($tmpDir);
    }

    public function testGetName()
    {
        $validator = new GitValidator(sys_get_temp_dir());
        $this->assertEquals('git', $validator->getName());
    }

    private function cleanupDir($dir)
    {
        if (is_dir($dir)) {
            $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($dir);
        }
    }
}
