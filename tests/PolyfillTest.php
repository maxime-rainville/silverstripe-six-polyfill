<?php

namespace SilverStripe\Tests;

use PHPUnit\Framework\TestCase;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Core\ArrayLib;
use SilverStripe\Forms\Validation\Validator;

/**
 * Test that polyfill classes can be instantiated and used
 */
class PolyfillTest extends TestCase
{
    public function testArrayListPolyfill()
    {
        $list = new ArrayList([1, 2, 3]);
        $this->assertInstanceOf(ArrayList::class, $list);
        $this->assertEquals(3, $list->count());
    }

    public function testArrayLibExists()
    {
        $this->assertTrue(class_exists(\SilverStripe\Core\ArrayLib::class));
    }

    public function testValidatorExists()
    {
        $this->assertTrue(class_exists(\SilverStripe\Forms\Validation\Validator::class));
    }
}