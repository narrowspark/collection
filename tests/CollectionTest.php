<?php
declare(strict_types=1);
namespace Narrowspark\Collection\Tests;

use ArrayIterator;
use CachingIterator;
use Narrowspark\Collection\Collection;

class CollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testCountable()
    {
        $c = new Collection(['foo', 'bar']);

        $this->assertCount(2, $c);
    }

    public function testIterable()
    {
        $c = new Collection(['foo']);

        $this->assertInstanceOf(ArrayIterator::class, $c->getIterator());
        $this->assertEquals(['foo'], $c->getIterator()->getArrayCopy());
    }

    public function testCachingIterator()
    {
        $c = new Collection(['foo']);

        $this->assertInstanceOf(CachingIterator::class, $c->getCachingIterator());
    }
}
