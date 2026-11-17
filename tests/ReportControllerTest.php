<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Vendor\Validator\Controllers\ReportController;
use Vendor\Validator\Services\ReportPresenter;
use Vendor\Validator\Services\ValidatorService;

class ReportControllerTest extends TestCase
{
    public function testEscapeValuesEscapesNestedStrings()
    {
        $controller = $this->controller();
        $method = new ReflectionMethod($controller, 'escapeValues');
        $method->setAccessible(true);

        $escaped = $method->invoke($controller, array(
            'title' => '<b>Titulo</b>',
            'items' => array('a & b', 'c "d"'),
            'counts' => array('validators' => 3, 'success' => true),
        ));

        $this->assertSame('&lt;b&gt;Titulo&lt;/b&gt;', $escaped['title']);
        $this->assertSame('a &amp; b', $escaped['items'][0]);
        $this->assertSame('c &quot;d&quot;', $escaped['items'][1]);
        $this->assertSame(3, $escaped['counts']['validators']);
        $this->assertTrue($escaped['counts']['success']);
    }

    public function testEscapeValuesIgnoresNullAndScalars()
    {
        $controller = $this->controller();
        $method = new ReflectionMethod($controller, 'escapeValues');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller, null));
        $this->assertSame(12, $method->invoke($controller, 12));
        $this->assertSame(1.5, $method->invoke($controller, 1.5));
    }

    /**
     * @return ReportController
     */
    private function controller()
    {
        return new ReportController(
            $this->getMockBuilder(ValidatorService::class)->disableOriginalConstructor()->getMock(),
            new ReportPresenter()
        );
    }
}
