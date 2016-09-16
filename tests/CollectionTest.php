<?php
declare(strict_types=1);
namespace Narrowspark\Collection\Tests;

use ArrayIterator;
use CachingIterator;
use Narrowspark\Collection\Collection;
use Narrowspark\Collection\Tests\Fixture\TestArrayableObjectFixture;
use Narrowspark\Collection\Tests\Fixture\TestJsonableObjectFixture;
use Narrowspark\Collection\Tests\Fixture\TestJsonSerializeObjectFixture;

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

    public function testJsonSerialize()
    {
        $c = new Collection([
            new TestArrayableObjectFixture(),
            new TestJsonableObjectFixture(),
            new TestJsonSerializeObjectFixture(),
            'baz',
        ]);
        $this->assertSame([
            ['foo' => 'bar'],
            ['foo' => 'bar'],
            ['foo' => 'bar'],
            'baz',
        ], $c->jsonSerialize());
    }

    public function testRegisterExtensions()
    {
        Collection::extend(__CLASS__, function () {
            return ['foo'];
        });

        $this->assertEquals(['foo'], Collection::{__CLASS__}());
    }

    public function testWhenCallingExtensionsClosureIsBoundToObject()
    {
        Collection::extend('tryAll', function () {
            return $this->all();
        });

        $c = new Collection();

        $this->assertEquals([], $c->tryAll());
        $this->assertTrue($c->hasExtensions('tryAll'));
    }

    public function testAppend()
    {
        $c = new Collection([1, 3, 3, 2]);

        $this->assertEquals([1, 3, 3, 2, 1], $c->append(1)->values()->all());
    }

    public function testOnly()
    {
        $c = new Collection(['first' => 'Foo', 'last' => 'Bar', 'baz' => 'test']);

        $this->assertEquals(['first' => 'Foo'], $c->only(['first', 'missing'])->all());
        $this->assertEquals(['first' => 'Foo'], $c->only('first', 'missing')->all());
        $this->assertEquals(['first' => 'Foo', 'baz' => 'test'], $c->only(['first', 'baz'])->all());
        $this->assertEquals(['first' => 'Foo', 'baz' => 'test'], $c->only('first', 'baz')->all());
    }

    public function testPullRetrievesItemFromCollection()
    {
        $c = new Collection(['foo', 'bar']);

        $this->assertEquals('foo', $c->pull(0));
    }

    public function testPullRemovesItemFromCollection()
    {
        $c = new Collection(['foo', 'bar']);
        $c->pull(0);

        $this->assertEquals([1 => 'bar'], $c->all());
    }

    public function testPullReturnsDefault()
    {
        $c = new Collection([]);

        $this->assertEquals('foo', $c->pull(0, 'foo'));
    }

}
