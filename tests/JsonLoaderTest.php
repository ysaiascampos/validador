<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Adapters\JsonLoader;
use Vendor\Validator\Exceptions\ValidationException;

class JsonLoaderTest extends TestCase
{
    public function testParseValidJson()
    {
        $loader = new JsonLoader();
        $result = $loader->parse('{"connection":"oracle"}');

        $this->assertEquals(array('connection' => 'oracle'), $result);
    }

    public function testParseInvalidJsonThrowsException()
    {
        $this->expectException(ValidationException::class);

        $loader = new JsonLoader();
        $loader->parse('{invalid json}');
    }

    public function testParseEmptyStringThrowsException()
    {
        $this->expectException(ValidationException::class);

        $loader = new JsonLoader();
        $loader->parse('');
    }

    public function testParseJsonArray()
    {
        $loader = new JsonLoader();
        $result = $loader->parse('["a","b","c"]');

        $this->assertEquals(array('a', 'b', 'c'), $result);
    }

    public function testParseNestedJson()
    {
        $loader = new JsonLoader();
        $json = '{"database":{"schemas":["SCHEMA1","SCHEMA2"],"tables":["TABLE1"]}}';
        $result = $loader->parse($json);

        $this->assertArrayHasKey('database', $result);
        $this->assertCount(2, $result['database']['schemas']);
    }

    public function testLoadNonExistentFileThrowsException()
    {
        $this->expectException(ValidationException::class);

        $loader = new JsonLoader();
        $loader->load('/tmp/nonexistent_file_' . uniqid() . '.json');
    }
}
