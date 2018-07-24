<?php
declare(strict_types=1);
namespace Narrowspark\Collection\Tests;

use ArrayIterator;
use ArrayObject;
use CachingIterator;
use JsonSerializable;
use Narrowspark\Collection\Collection;
use Narrowspark\Collection\Tests\Fixture\TestArrayableObjectFixture;
use Narrowspark\Collection\Tests\Fixture\TestArrayAccessImplementationFixture;
use Narrowspark\Collection\Tests\Fixture\TestJsonableObjectFixture;
use Narrowspark\Collection\Tests\Fixture\TestJsonSerializeObjectFixture;
use ReflectionClass;
use stdClass;

/**
 * @internal
 */
final class CollectionTest extends \PHPUnit\Framework\TestCase
{
    public function testCountable()
    {
        $c = new Collection(['foo', 'bar']);

        static::assertCount(2, $c);
    }

    public function testIterable()
    {
        $c = new Collection(['foo']);

        static::assertInstanceOf(ArrayIterator::class, $c->getIterator());
        static::assertEquals(['foo'], $c->getIterator()->getArrayCopy());
    }

    public function testCachingIterator()
    {
        $c = new Collection(['foo']);

        static::assertInstanceOf(CachingIterator::class, $c->getCachingIterator());
    }

    public function testJsonSerialize()
    {
        $c = new Collection([
            new TestArrayableObjectFixture(),
            new TestJsonableObjectFixture(),
            new TestJsonSerializeObjectFixture(),
            'baz',
        ]);
        static::assertSame([
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

        static::assertEquals(['foo'], Collection::{__CLASS__}());
    }

    public function testWhenCallingExtensionsClosureIsBoundToObject()
    {
        Collection::extend('tryAll', function () {
            return $this->all();
        });

        $c = new Collection();

        static::assertEquals([], $c->tryAll());
        static::assertTrue($c->hasExtensions('tryAll'));
    }

    public function testExtend()
    {
        Collection::extend('foo', function () {
            return $this->filter(function ($key, $value) {
                return \mb_strpos($value, 'a') === 0;
            })
                ->unique()
                ->values();
        });

        $c = new Collection(['a', 'a', 'aa', 'aaa', 'bar']);

        static::assertSame(['a', 'aa', 'aaa'], $c->foo()->all());
    }

    public function testAppend()
    {
        $c = new Collection([1, 3, 3, 2]);

        static::assertEquals([1, 3, 3, 2, 1], $c->append(1)->values()->all());
    }

    public function testOnly()
    {
        $c = new Collection(['first' => 'Foo', 'last' => 'Bar', 'baz' => 'test']);

        static::assertEquals(['first' => 'Foo'], $c->only(['first', 'missing'])->all());
        static::assertEquals(['first' => 'Foo'], $c->only('first', 'missing')->all());
        static::assertEquals(['first' => 'Foo', 'baz' => 'test'], $c->only(['first', 'baz'])->all());
        static::assertEquals(['first' => 'Foo', 'baz' => 'test'], $c->only('first', 'baz')->all());
    }

    public function testPullRetrievesItemFromCollection()
    {
        $c = new Collection(['foo', 'bar']);

        static::assertEquals('foo', $c->pull(0));
    }

    public function testPullRemovesItemFromCollection()
    {
        $c = new Collection(['foo', 'bar']);
        $c->pull(0);

        static::assertEquals([1 => 'bar'], $c->all());
    }

    public function testPullReturnsDefault()
    {
        $c = new Collection([]);

        static::assertEquals('foo', $c->pull(0, 'foo'));
    }

    public function testFirstReturnsFirstItemInCollection()
    {
        $c = new Collection(['foo', 'bar']);

        static::assertEquals('foo', $c->first());
    }

    public function testLastReturnsLastItemInCollection()
    {
        $c = new Collection(['foo', 'bar']);
        static::assertEquals('bar', $c->last());
    }

    public function testLastWithCallback()
    {
        $data = new Collection([100, 200, 300]);

        $result = $data->last(function ($key, $value) {
            return $value < 250;
        });

        static::assertEquals(200, $result);

        $result = $data->last(function ($key) {
            return $key < 2;
        });

        static::assertEquals(200, $result);
    }

    public function testLastWithCallbackAndDefault()
    {
        $data   = new Collection(['foo', 'bar']);
        $result = $data->last(function ($value) {
            return $value === 'baz';
        }, 'default');

        static::assertEquals('default', $result);
    }

    public function testLastWithDefaultAndWithoutCallback()
    {
        $data   = new Collection();
        $result = $data->last(null, 'default');

        static::assertEquals('default', $result);
    }

    public function testPopReturnsAndRemovesLastItemInCollection()
    {
        $c = new Collection(['foo', 'bar']);

        static::assertEquals('bar', $c->pop());
        static::assertEquals('foo', $c->first());
    }

    public function testShiftReturnsAndRemovesFirstItemInCollection()
    {
        $c = new Collection(['foo', 'bar']);

        static::assertEquals('foo', $c->shift());
        static::assertEquals('bar', $c->first());
    }

    public function testEmptyCollectionIsEmpty()
    {
        $c = new Collection();

        static::assertTrue($c->isEmpty());
    }

    public function testCollectionIsConstructed()
    {
        $c = new Collection('foo');

        static::assertSame(['foo'], $c->all());

        $c = new Collection(2);

        static::assertSame([2], $c->all());

        $c = new Collection(false);

        static::assertSame([false], $c->all());

        $c = new Collection(null);

        static::assertSame([], $c->all());

        $c = new Collection();

        static::assertSame([], $c->all());
    }

    public function testGetArrayableItems()
    {
        $c     = new Collection();
        $class = new ReflectionClass($c);

        $method = $class->getMethod('getArrayableItems');
        $method->setAccessible(true);

        $items = new TestArrayableObjectFixture();
        $array = $method->invokeArgs($c, [$items]);

        static::assertSame(['foo' => 'bar'], $array);

        $items = new TestJsonableObjectFixture();
        $array = $method->invokeArgs($c, [$items]);

        static::assertSame(['foo' => 'bar'], $array);

        $items = new TestJsonSerializeObjectFixture();
        $array = $method->invokeArgs($c, [$items]);

        static::assertSame(['foo' => 'bar'], $array);

        $items = new Collection(['foo' => 'bar']);
        $array = $method->invokeArgs($c, [$items]);

        static::assertSame(['foo' => 'bar'], $array);

        $array = $method->invokeArgs($c, [['foo' => 'bar']]);

        static::assertSame(['foo' => 'bar'], $array);
    }

    public function testToArrayCallsToArrayOnEachItemInCollection()
    {
        $item1 = $this->getMockBuilder(stdClass::class)
            ->setMethods(['toArray'])
            ->getMock();
        $item1->expects(static::once())
            ->method('toArray')
            ->will(static::returnValue('foo.array'));

        $item2 = $this->getMockBuilder(stdClass::class)
            ->setMethods(['toArray'])
            ->getMock();
        $item2->expects(static::once())
            ->method('toArray')
            ->will(static::returnValue('bar.array'));

        $c = new Collection([$item1, $item2]);

        static::assertEquals(['foo.array', 'bar.array'], $c->toArray());
    }

    public function testJsonSerializeCallsToArrayOrJsonSerializeOnEachItemInCollection()
    {
        $item1 = $this->getMockBuilder(JsonSerializable::class)
            ->setMethods(['jsonSerialize'])
            ->getMock();
        $item1->expects(static::once())
            ->method('jsonSerialize')
            ->will(static::returnValue('foo.json'));

        $item2 = $this->getMockBuilder(stdClass::class)
            ->setMethods(['toArray'])
            ->getMock();
        $item2->expects(static::once())
            ->method('toArray')
            ->will(static::returnValue('bar.array'));

        $c = new Collection([$item1, $item2]);

        static::assertEquals(['foo.json', 'bar.array'], $c->jsonSerialize());
    }

    public function testToJsonEncodesTheJsonSerializeResult()
    {
        $c = $this->getMockBuilder(Collection::class)
            ->setMethods(['jsonSerialize'])
            ->getMock();
        $c->expects(static::once())
            ->method('jsonSerialize')
            ->will(static::returnValue('foo'));

        static::assertJsonStringEqualsJsonString(\json_encode('foo'), $c->toJson());
    }

    public function testCastingToStringJsonEncodesTheToArrayResult()
    {
        $c = $this->getMockBuilder(Collection::class)
            ->setMethods(['jsonSerialize'])
            ->getMock();
        $c->expects(static::once())
            ->method('jsonSerialize')
            ->will(static::returnValue('foo'));

        static::assertJsonStringEqualsJsonString(\json_encode('foo'), (string) $c);
    }

    public function testOffsetAccess()
    {
        $c = new Collection(['type' => 'collection']);

        static::assertEquals('collection', $c['type']);

        $c['type'] = 'array';

        static::assertEquals('array', $c['type']);
        static::assertTrue(isset($c['type']));

        unset($c['type']);

        static::assertFalse(isset($c['type']));

        $c[] = 'json';

        static::assertEquals('json', $c[0]);
    }

    public function testArrayAccessOffsetExists()
    {
        $c = new Collection(['foo', 'bar']);

        static::assertTrue($c->offsetExists(0));
        static::assertTrue($c->offsetExists(1));
        static::assertFalse($c->offsetExists(1000));
    }

    public function testArrayAccessOffsetGet()
    {
        $c = new Collection(['foo', 'bar']);

        static::assertEquals('foo', $c->offsetGet(0));
        static::assertEquals('bar', $c->offsetGet(1));
    }

    public function testArrayAccessOffsetGetOnNonExist()
    {
        $this->expectException(\PHPUnit\Framework\Error\Notice::class);

        $c = new Collection(['foo', 'bar']);

        $c->offsetGet(1000);
    }

    public function testArrayAccessOffsetSet()
    {
        $c = new Collection(['foo', 'foo']);
        $c->offsetSet(1, 'bar');
        static::assertEquals('bar', $c[1]);
        $c->offsetSet(null, 'qux');
        static::assertEquals('qux', $c[2]);
    }

    public function testArrayAccessOffsetUnset()
    {
        $this->expectException(\PHPUnit\Framework\Error\Notice::class);

        $c = new Collection(['foo', 'bar']);
        $c->offsetUnset(1);
        $c[1];
    }

    public function testForgetSingleKey()
    {
        $c = new Collection(['foo', 'bar']);
        $c->forget(0);

        static::assertFalse(isset($c['foo']));

        $c = new Collection(['foo' => 'bar', 'baz' => 'qux']);
        $c->forget('foo');

        static::assertFalse(isset($c['foo']));
    }

    public function testForgetArrayOfKeys()
    {
        $c = new Collection(['foo', 'bar', 'baz']);
        $c->forget([0, 2]);

        static::assertFalse(isset($c[0]));
        static::assertFalse(isset($c[2]));
        static::assertTrue(isset($c[1]));

        $c = new Collection(['foo' => 'bar', 'type' => 'collection', 'baz' => 'qux']);
        $c->forget(['foo', 'baz']);

        static::assertFalse(isset($c['foo']));
        static::assertFalse(isset($c['baz']));
        static::assertTrue(isset($c['type']));
    }

    public function testFilter()
    {
        $c = new Collection([['id' => 1, 'name' => 'Hello'], ['id' => 2, 'name' => 'World']]);

        static::assertEquals([1 => ['id' => 2, 'name' => 'World']], $c->filter(function ($key, $value) {
            return $value['id'] === 2;
        })->all());

        $c = new Collection(['', 'Hello', '', 'World']);

        static::assertEquals(['Hello', 'World'], $c->filter()->values()->toArray());

        $c = new Collection(['id' => 1, 'first' => 'Hello', 'second' => 'World']);

        static::assertEquals(['first' => 'Hello', 'second' => 'World'], $c->filter(function ($key) {
            return $key !== 'id';
        })->all());
    }

    public function testWhere()
    {
        $c = new Collection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);

        static::assertEquals(
            [['v' => 3], ['v' => '3']],
            $c->where('v', 3)->values()->all()
        );
        static::assertEquals(
            [['v' => 3], ['v' => '3']],
            $c->where('v', '=', 3)->values()->all()
        );
        static::assertEquals(
            [['v' => 3], ['v' => '3']],
            $c->where('v', '==', 3)->values()->all()
        );
        static::assertEquals(
            [['v' => 3], ['v' => '3']],
            $c->where('v', 'garbage', 3)->values()->all()
        );
        static::assertEquals(
            [['v' => 3]],
            $c->where('v', '===', 3)->values()->all()
        );
        static::assertEquals(
            [['v' => 1], ['v' => 2], ['v' => 4]],
            $c->where('v', '<>', 3)->values()->all()
        );
        static::assertEquals(
            [['v' => 1], ['v' => 2], ['v' => 4]],
            $c->where('v', '!=', 3)->values()->all()
        );
        static::assertEquals(
            [['v' => 1], ['v' => 2], ['v' => '3'], ['v' => 4]],
            $c->where('v', '!==', 3)->values()->all()
        );
        static::assertEquals(
            [['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3']],
            $c->where('v', '<=', 3)->values()->all()
        );
        static::assertEquals(
            [['v' => 3], ['v' => '3'], ['v' => 4]],
            $c->where('v', '>=', 3)->values()->all()
        );
        static::assertEquals(
            [['v' => 1], ['v' => 2]],
            $c->where('v', '<', 3)->values()->all()
        );
        static::assertEquals(
            [['v' => 4]],
            $c->where('v', '>', 3)->values()->all()
        );
    }

    public function testWhereStrict()
    {
        $c = new Collection([['v' => 3], ['v' => '3']]);

        static::assertEquals(
            [['v' => 3]],
            $c->whereStrict('v', 3)->values()->all()
        );
    }

    public function testWhereIn()
    {
        $c = new Collection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);

        static::assertEquals([['v' => 1], ['v' => 3], ['v' => '3']], $c->whereIn('v', [1, 3])->values()->all());
    }

    public function testWhereInStrict()
    {
        $c = new Collection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);

        static::assertEquals([['v' => 1], ['v' => 3]], $c->whereInStrict('v', [1, 3])->values()->all());
    }

    public function testValues()
    {
        $c = new Collection([['id' => 1, 'name' => 'Hello'], ['id' => 2, 'name' => 'World']]);

        static::assertEquals([['id' => 2, 'name' => 'World']], $c->filter(function ($key, $item) {
            return $item['id'] === 2;
        })->values()->all());
    }

    public function testFlatten()
    {
        // Flat arrays are unaffected
        $c = new Collection(['#foo', '#bar', '#baz']);

        static::assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

        // Nested arrays are flattened with existing flat items
        $c = new Collection([['#foo', '#bar'], '#baz']);

        static::assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

        // Sets of nested arrays are flattened
        $c = new Collection([['#foo', '#bar'], ['#baz']]);

        static::assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

        // Deeply nested arrays are flattened
        $c = new Collection([['#foo', ['#bar']], ['#baz']]);

        static::assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

        // Nested collections are flattened alongside arrays
        $c = new Collection([new Collection(['#foo', '#bar']), ['#baz']]);

        static::assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

        // Nested collections containing plain arrays are flattened
        $c = new Collection([new Collection(['#foo', ['#bar']]), ['#baz']]);

        static::assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

        // Nested arrays containing collections are flattened
        $c = new Collection([['#foo', new Collection(['#bar'])], ['#baz']]);

        static::assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

        // Nested arrays containing collections containing arrays are flattened
        $c = new Collection([['#foo', new Collection(['#bar', ['#zap']])], ['#baz']]);

        static::assertEquals(['#foo', '#bar', '#zap', '#baz'], $c->flatten()->all());
    }

    public function testFlattenWithDepth()
    {
        // No depth flattens recursively
        $c = new Collection([['#foo', ['#bar', ['#baz']]], '#zap']);

        static::assertEquals(['#foo', '#bar', '#baz', '#zap'], $c->flatten()->all());

        // Specifying a depth only flattens to that depth
        $c = new Collection([['#foo', ['#bar', ['#baz']]], '#zap']);

        static::assertEquals(['#foo', ['#bar', ['#baz']], '#zap'], $c->flatten(1)->all());

        $c = new Collection([['#foo', ['#bar', ['#baz']]], '#zap']);

        static::assertEquals(['#foo', '#bar', ['#baz'], '#zap'], $c->flatten(2)->all());
    }

    public function testFlattenIgnoresKeys()
    {
        // No depth ignores keys
        $c = new Collection(['#foo', ['key' => '#bar'], ['key' => '#baz'], 'key' => '#zap']);

        static::assertEquals(['#foo', '#bar', '#baz', '#zap'], $c->flatten()->all());

        // Depth of 1 ignores keys
        $c = new Collection(['#foo', ['key' => '#bar'], ['key' => '#baz'], 'key' => '#zap']);

        static::assertEquals(['#foo', '#bar', '#baz', '#zap'], $c->flatten(1)->all());
    }

    public function testMergeNull()
    {
        $c = new Collection(['name' => 'Hello']);

        static::assertEquals(['name' => 'Hello'], $c->merge(null)->all());
    }

    public function testMergeArray()
    {
        $c = new Collection(['name' => 'Hello']);

        static::assertEquals(['name' => 'Hello', 'id' => 1], $c->merge(['id' => 1])->all());
    }

    public function testMergeCollection()
    {
        $c = new Collection(['name' => 'Hello']);

        static::assertEquals(
            ['name' => 'World', 'id' => 1],
            $c->merge(new Collection(['name' => 'World', 'id' => 1]))->all()
        );
    }

    public function testUnionNull()
    {
        $c = new Collection(['name' => 'Hello']);

        static::assertEquals(['name' => 'Hello'], $c->union(null)->all());
    }

    public function testUnionArray()
    {
        $c = new Collection(['name' => 'Hello']);

        static::assertEquals(['name' => 'Hello', 'id' => 1], $c->union(['id' => 1])->all());
    }

    public function testUnionCollection()
    {
        $c = new Collection(['name' => 'Hello']);

        static::assertEquals(
            ['name' => 'Hello', 'id' => 1],
            $c->union(new Collection(['name' => 'World', 'id' => 1]))->all()
        );
    }

    public function testDiffCollection()
    {
        $c = new Collection(['id' => 1, 'first_word' => 'Hello']);

        static::assertEquals(
            ['id' => 1],
            $c->diff(new Collection(['first_word' => 'Hello', 'last_word' => 'World']))->all()
        );
    }

    public function testDiffNull()
    {
        $c = new Collection(['id' => 1, 'first_word' => 'Hello']);

        static::assertEquals(['id' => 1, 'first_word' => 'Hello'], $c->diff(null)->all());
    }

    public function testDiffKeys()
    {
        $c1 = new Collection(['id' => 1, 'first_word' => 'Hello']);
        $c2 = new Collection(['id' => 123, 'foo_bar' => 'Hello']);

        static::assertEquals(['first_word' => 'Hello'], $c1->diffKeys($c2)->all());
    }

    public function testEach()
    {
        $c      = new Collection($original = [1, 2, 'foo' => 'bar', 'bam' => 'baz']);
        $result = [];

        $c->each(function ($item, $key) use (&$result) {
            $result[$key] = $item;
        });

        static::assertEquals($original, $result);

        $result = [];

        $c->each(function ($item, $key) use (&$result) {
            $result[$key] = $item;

            if (\is_string($key)) {
                return false;
            }
        });

        static::assertEquals([1, 2, 'foo' => 'bar'], $result);
    }

    public function testIntersectNull()
    {
        $c = new Collection(['id' => 1, 'first_word' => 'Hello']);

        static::assertEquals([], $c->intersect(null)->all());
    }

    public function testIntersectCollection()
    {
        $c = new Collection(['id' => 1, 'first_word' => 'Hello']);

        static::assertEquals(
            ['first_word' => 'Hello'],
            $c->intersect(new Collection(['first_world' => 'Hello', 'last_word' => 'World']))->all()
        );
    }

    public function testUnique()
    {
        $c = new Collection(['Hello', 'World', 'World']);

        static::assertEquals(['Hello', 'World'], $c->unique()->all());

        $c = new Collection([[1, 2], [1, 2], [2, 3], [3, 4], [2, 3]]);

        static::assertEquals([[1, 2], [2, 3], [3, 4]], $c->unique()->values()->all());
    }

    public function testUniqueWithCallback()
    {
        $c = new Collection([
            1 => ['id' => 1, 'first' => 'Narrowspark', 'last' => 'Collection'],
            2 => ['id' => 2, 'first' => 'Narrowspark', 'last' => 'Collection'],
            3 => ['id' => 3, 'first' => 'Framework', 'last' => 'Collection'],
            4 => ['id' => 4, 'first' => 'Framework', 'last' => 'Collection'],
            5 => ['id' => 5, 'first' => 'Narrowspark', 'last' => 'Swift'],
            6 => ['id' => 6, 'first' => 'Narrowspark', 'last' => 'Swift'],
        ]);

        static::assertEquals([
            1 => ['id' => 1, 'first' => 'Narrowspark', 'last' => 'Collection'],
            3 => ['id' => 3, 'first' => 'Framework', 'last' => 'Collection'],
        ], $c->unique('first')->all());

        static::assertEquals([
            1 => ['id' => 1, 'first' => 'Narrowspark', 'last' => 'Collection'],
            3 => ['id' => 3, 'first' => 'Framework', 'last' => 'Collection'],
            5 => ['id' => 5, 'first' => 'Narrowspark', 'last' => 'Swift'],
        ], $c->unique(function ($item) {
            return $item['first'] . $item['last'];
        })->all());
    }

    public function testUniqueStrict()
    {
        $c = new Collection([
            ['id' => '0', 'name' => 'zero'],
            ['id' => '00', 'name' => 'double zero'],
            ['id' => '0', 'name' => 'again zero'],
        ]);

        static::assertEquals([
            ['id' => '0', 'name' => 'zero'],
            ['id' => '00', 'name' => 'double zero'],
        ], $c->uniqueStrict('id')->all());
    }

    public function testCollapse()
    {
        $data = new Collection([[$object1 = new stdClass()], [$object2 = new stdClass()]]);

        static::assertEquals([$object1, $object2], $data->collapse()->all());
    }

    public function testCollapseWithNestedCollactions()
    {
        $data = new Collection([new Collection([1, 2, 3]), new Collection([4, 5, 6])]);

        static::assertEquals([1, 2, 3, 4, 5, 6], $data->collapse()->all());
    }

    public function testSort()
    {
        $data = (new Collection([5, 3, 1, 2, 4]))->sort();

        static::assertEquals([1, 2, 3, 4, 5], $data->values()->all());

        $data = (new Collection([-1, -3, -2, -4, -5, 0, 5, 3, 1, 2, 4]))->sort();

        static::assertEquals([-5, -4, -3, -2, -1, 0, 1, 2, 3, 4, 5], $data->values()->all());

        $data = (new Collection(['foo', 'bar-10', 'bar-1']))->sort();

        static::assertEquals(['bar-1', 'bar-10', 'foo'], $data->values()->all());
    }

    public function testSortWithCallback()
    {
        $data = (new Collection([5, 3, 1, 2, 4]))->sort(function ($a, $b) {
            if ($a === $b) {
                return 0;
            }

            return ($a < $b) ? -1 : 1;
        });
        static::assertEquals(\range(1, 5), \array_values($data->all()));
    }

    public function testSortBy()
    {
        $data = new Collection(['collection', 'narrowspark']);
        $data = $data->sortBy(function ($x) {
            return $x;
        });

        static::assertEquals(['collection', 'narrowspark'], \array_values($data->all()));

        $data = new Collection(['collection', 'narrowspark']);
        $data = $data->sortByDesc(function ($x) {
            return $x;
        });

        static::assertEquals(['narrowspark', 'collection'], \array_values($data->all()));
    }

    public function testSortByString()
    {
        $data = new Collection([['name' => 'collection'], ['name' => 'narrowspark']]);
        $data = $data->sortBy('name');

        static::assertEquals([['name' => 'collection'], ['name' => 'narrowspark']], \array_values($data->all()));
    }

    public function testReverse()
    {
        $data = new Collection(['zaeed', 'alan']);

        static::assertSame([1 => 'alan', 0 => 'zaeed'], $data->reverse()->all());

        $data = new Collection(['name' => 'collection', 'framework' => 'narrowspark']);

        static::assertSame(
            ['framework' => 'narrowspark', 'name' => 'collection'],
            $data->reverse()->all()
        );
    }

    public function testFlip()
    {
        $data = new Collection(['name' => 'collection', 'framework' => 'narrowspark']);

        static::assertEquals(
            ['collection' => 'name', 'narrowspark' => 'framework'],
            $data->flip()->toArray()
        );
    }

    public function testChunk()
    {
        $data = new Collection([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        $data = $data->chunk(3);

        static::assertInstanceOf(Collection::class, $data);
        static::assertInstanceOf(Collection::class, $data[0]);
        static::assertCount(4, $data);
        static::assertEquals([1, 2, 3], $data[0]->toArray());
        static::assertEquals([9 => 10], $data[3]->toArray());
    }

    public function testEvery()
    {
        $data = new Collection([
            6 => 'a',
            4 => 'b',
            7 => 'c',
            1 => 'd',
            5 => 'e',
            3 => 'f',
        ]);

        static::assertEquals(['a', 'e'], $data->every(4)->all());
        static::assertEquals(['b', 'f'], $data->every(4, 1)->all());
        static::assertEquals(['c'], $data->every(4, 2)->all());
        static::assertEquals(['d'], $data->every(4, 3)->all());
    }

    public function testExcept()
    {
        $data = new Collection(['first' => 'Narrowspark', 'last' => 'Collection', 'foo' => 'bar']);

        static::assertEquals(['first' => 'Narrowspark'], $data->except(['last', 'foo', 'missing'])->all());
        static::assertEquals(['first' => 'Narrowspark'], $data->except('last', 'foo', 'missing')->all());
        static::assertEquals(['first' => 'Narrowspark', 'foo' => 'bar'], $data->except(['last'])->all());
        static::assertEquals(['first' => 'Narrowspark', 'foo' => 'bar'], $data->except('last')->all());
    }

    public function testPluckWithArrayAndObjectValues()
    {
        $data = new Collection(
            [(object) ['name' => 'collection', 'email' => 'foo'],
                ['name' => 'foo', 'email' => 'bar'], ]
        );

        static::assertEquals(['collection' => 'foo', 'foo' => 'bar'], $data->pluck('email', 'name')->all());
        static::assertEquals(['foo', 'bar'], $data->pluck('email')->all());
    }

    public function testPluckWithArrayAccessValues()
    {
        $data = new Collection([
            new TestArrayAccessImplementationFixture(['name' => 'collection', 'email' => 'foo']),
            new TestArrayAccessImplementationFixture(['name' => 'dayle', 'email' => 'bar']),
        ]);

        static::assertEquals(
            ['collection' => 'foo', 'dayle' => 'bar'],
            $data->pluck('email', 'name')->all()
        );

        static::assertEquals(['foo', 'bar'], $data->pluck('email')->all());
    }

    public function testImplode()
    {
        $data = new Collection([['name' => 'collection', 'email' => 'foo'], ['name' => 'foo', 'email' => 'bar']]);

        static::assertEquals('foobar', $data->implode('email'));
        static::assertEquals('foo,bar', $data->implode('email', ','));

        $data = new Collection(['collection', 'foo']);

        static::assertEquals('collectionfoo', $data->implode(''));
        static::assertEquals('collection,foo', $data->implode(','));
    }

    public function testTake()
    {
        $data = new Collection(['collection', 'foo', 'bar']);
        $data = $data->take(2);

        static::assertEquals(['collection', 'foo'], $data->all());
    }

    public function testRandom()
    {
        $data = new Collection([1, 2, 3, 4, 5, 6]);

        static::assertInternalType('integer', $data->random());
        static::assertContains($data->random(), $data->all());

        static::assertInstanceOf(Collection::class, $data->random(3));
        static::assertCount(3, $data->random(3));
    }

    public function testRandomThrowsAnErrorWhenRequestingMoreItemsThanAreAvailable()
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Collection())->random();
    }

    public function testTakeLast()
    {
        $data = new Collection(['collection', 'foo', 'bar']);
        $data = $data->take(-2);

        static::assertEquals([1 => 'foo', 2 => 'bar'], $data->all());
    }

    public function testFromMethod()
    {
        $c = Collection::from('foo');

        static::assertEquals(['foo'], $c->all());
    }

    public function testFromMethodFromNull()
    {
        $c = Collection::from(null);

        static::assertEquals([], $c->all());

        $c = Collection::from();

        static::assertEquals([], $c->all());
    }

    public function testFromMethodFromCollection()
    {
        $firstCollection  = Collection::from(['foo' => 'bar']);
        $secondCollection = Collection::from($firstCollection);

        static::assertEquals(['foo' => 'bar'], $secondCollection->all());
    }

    public function testFromMethodFromArray()
    {
        $c = Collection::from(['foo' => 'bar']);

        static::assertEquals(['foo' => 'bar'], $c->all());
    }

    public function testConstructFromFromObject()
    {
        $object      = new stdClass();
        $object->foo = 'bar';

        $c = Collection::from($object);

        static::assertEquals(['foo' => 'bar'], $c->all());
    }

    public function testConstructMethod()
    {
        $c = new Collection('foo');

        static::assertEquals(['foo'], $c->all());
    }

    public function testConstructMethodFromNull()
    {
        $c = new Collection(null);

        static::assertEquals([], $c->all());

        $c = new Collection();

        static::assertEquals([], $c->all());
    }

    public function testConstructMethodFromCollection()
    {
        $firstCollection  = new Collection(['foo' => 'bar']);
        $secondCollection = new Collection($firstCollection);

        static::assertEquals(['foo' => 'bar'], $secondCollection->all());
    }

    public function testConstructMethodFromArray()
    {
        $c = new Collection(['foo' => 'bar']);

        static::assertEquals(['foo' => 'bar'], $c->all());
    }

    public function testConstructMethodFromObject()
    {
        $object      = new stdClass();
        $object->foo = 'bar';

        $c = new Collection($object);

        static::assertEquals(['foo' => 'bar'], $c->all());
    }

    public function testSplice()
    {
        $data = new Collection(['foo', 'baz']);
        $data->splice(1);

        static::assertEquals(['foo'], $data->all());

        $data = new Collection(['foo', 'baz']);
        $data->splice(1, 0, 'bar');

        static::assertEquals(['foo', 'bar', 'baz'], $data->all());

        $data = new Collection(['foo', 'baz']);
        $data->splice(1, 1);

        static::assertEquals(['foo'], $data->all());

        $data = new Collection(['foo', 'baz']);
        $cut  = $data->splice(1, 1, 'bar');

        static::assertEquals(['foo', 'bar'], $data->all());
        static::assertEquals(['baz'], $cut->all());
    }

    public function testMap()
    {
        $data = new Collection(['first' => 'bar', 'last' => 'foo']);
        $data = $data->map(function ($item, $key) {
            return $key . '-' . \strrev($item);
        });

        static::assertEquals(['first' => 'first-rab', 'last' => 'last-oof'], $data->all());
    }

    public function testFlatMap()
    {
        $data = new Collection([
            ['name' => 'mike', 'hobbies' => ['programming', 'basketball']],
            ['name' => 'nick', 'hobbies' => ['music', 'powerlifting']],
        ]);

        $data = $data->flatMap(function ($person) {
            return $person['hobbies'];
        });

        static::assertEquals(['programming', 'basketball', 'music', 'powerlifting'], $data->all());
    }

    public function testMapWithKeys()
    {
        $data = new Collection([
            ['name' => 'Blastoise', 'type' => 'Water', 'idx' => 9],
            ['name' => 'Charmander', 'type' => 'Fire', 'idx' => 4],
            ['name' => 'Dragonair', 'type' => 'Dragon', 'idx' => 148],
        ]);
        $data = $data->mapWithKeys(function ($pokemon) {
            return [$pokemon['name'] => $pokemon['type']];
        });

        static::assertEquals(
            ['Blastoise' => 'Water', 'Charmander' => 'Fire', 'Dragonair' => 'Dragon'],
            $data->all()
        );
    }

    public function testTransform()
    {
        $data = new Collection(['first' => 'foo', 'last' => 'bar']);

        $data->transform(function ($item, $key) {
            return $key . '-' . \strrev($item);
        });

        static::assertEquals(['first' => 'first-oof', 'last' => 'last-rab'], $data->all());
    }

    public function testFirstWithCallback()
    {
        $data = new Collection(['foo', 'bar', 'baz']);

        $result = $data->first(function ($key, $value) {
            return $value === 'bar';
        });

        static::assertEquals('bar', $result);
    }

    public function testFirstWithCallbackAndDefault()
    {
        $data = new Collection(['foo', 'bar']);

        $result = $data->first(function ($key, $value) {
            return $value === 'baz';
        }, 'default');

        static::assertEquals('default', $result);
    }

    public function testFirstWithDefaultAndWithoutCallback()
    {
        $data = new Collection();

        static::assertEquals('default', $data->first(null, 'default'));
    }

    public function testGroupByAttribute()
    {
        $data = new Collection([
            ['rating' => 1, 'url' => '1'],
            ['rating' => 1, 'url' => '1'],
            ['rating' => 2, 'url' => '2'],
        ]);

        static::assertEquals(
            [
                1 => [['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1']],
                2 => [['rating' => 2, 'url' => '2']],
            ],
            $data->groupBy('rating')->toArray()
        );

        static::assertEquals(
            [
                1 => [['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1']],
                2 => [['rating' => 2, 'url' => '2']],
            ],
            $data->groupBy('url')->toArray()
        );
    }

    public function testGroupByAttributePreservingKeys()
    {
        $data = new Collection([
            10 => ['rating' => 1, 'url' => '1'],
            20 => ['rating' => 1, 'url' => '1'],
            30 => ['rating' => 2, 'url' => '2'],
        ]);

        $expected = [
            1 => [10 => ['rating' => 1, 'url' => '1'], 20 => ['rating' => 1, 'url' => '1']],
            2 => [30 => ['rating' => 2, 'url' => '2']],
        ];
        static::assertEquals($expected, $data->groupBy('rating', true)->toArray());
    }

    public function testGroupByClosureWhereItemsHaveSingleGroup()
    {
        $data = new Collection([
            ['rating' => 1, 'url' => '1'],
            ['rating' => 1, 'url' => '1'],
            ['rating' => 2, 'url' => '2'],
        ]);
        $result = $data->groupBy(function ($item) {
            return $item['rating'];
        });

        static::assertEquals(
            [
                1 => [['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1']],
                2 => [['rating' => 2, 'url' => '2']],
            ],
            $result->toArray()
        );
    }

    public function testGroupByClosureWhereItemsHaveSingleGroupPreservingKeys()
    {
        $data = new Collection([
            10 => ['rating' => 1, 'url' => '1'],
            20 => ['rating' => 1, 'url' => '1'],
            30 => ['rating' => 2, 'url' => '2'],
        ]);
        $result = $data->groupBy(function ($item) {
            return $item['rating'];
        }, true);
        $expected = [
            1 => [10 => ['rating' => 1, 'url' => '1'], 20 => ['rating' => 1, 'url' => '1']],
            2 => [30 => ['rating' => 2, 'url' => '2']],
        ];

        static::assertEquals($expected, $result->toArray());
    }

    public function testGroupByClosureWhereItemsHaveMultipleGroups()
    {
        $data = new Collection([
            ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
            ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
            ['user' => 3, 'roles' => ['Role_1']],
        ]);
        $result = $data->groupBy(function ($item) {
            return $item['roles'];
        });
        $expected = [
            'Role_1' => [
                ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
                ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
                ['user' => 3, 'roles' => ['Role_1']],
            ],
            'Role_2' => [
                ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
            ],
            'Role_3' => [
                ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
            ],
        ];

        static::assertEquals($expected, $result->toArray());
    }

    public function testGroupByClosureWhereItemsHaveMultipleGroupsPreservingKeys()
    {
        $data = new Collection([
            10 => ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
            20 => ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
            30 => ['user' => 3, 'roles' => ['Role_1']],
        ]);
        $result = $data->groupBy(function ($item) {
            return $item['roles'];
        }, true);
        $expected = [
            'Role_1' => [
                10 => ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
                20 => ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
                30 => ['user' => 3, 'roles' => ['Role_1']],
            ],
            'Role_2' => [
                20 => ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
            ],
            'Role_3' => [
                10 => ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
            ],
        ];
        static::assertEquals($expected, $result->toArray());
    }

    public function testKeyByAttribute()
    {
        $data = new Collection([
            ['rating' => 1, 'name' => '1'],
            ['rating' => 2, 'name' => '2'],
            ['rating' => 3, 'name' => '3'],
        ]);

        $result = $data->keyBy('rating');

        static::assertEquals(
            [
                1 => ['rating' => 1, 'name' => '1'],
                2 => ['rating' => 2, 'name' => '2'],
                3 => ['rating' => 3, 'name' => '3'],
            ],
            $result->all()
        );

        $result = $data->keyBy(function ($item) {
            return $item['rating'] * 2;
        });

        static::assertEquals(
            [
                2 => ['rating' => 1, 'name' => '1'],
                4 => ['rating' => 2, 'name' => '2'],
                6 => ['rating' => 3, 'name' => '3'],
            ],
            $result->all()
        );
    }

    public function testKeyByClosure()
    {
        $data = new Collection([
            ['firstname' => 'Foo', 'lastname' => 'Bar', 'locale' => 'Uk'],
            ['firstname' => 'Bar', 'lastname' => 'Foo', 'locale' => 'US'],
        ]);
        $result = $data->keyBy(function ($item, $key) {
            return \mb_strtolower($key . '-' . $item['firstname'] . $item['lastname']);
        });

        static::assertEquals([
            '0-foobar' => ['firstname' => 'Foo', 'lastname' => 'Bar', 'locale' => 'Uk'],
            '1-barfoo' => ['firstname' => 'Bar', 'lastname' => 'Foo', 'locale' => 'US'],
        ], $result->all());
    }

    public function testContains()
    {
        $c = new Collection([1, 3, 5]);

        static::assertTrue($c->contains(1));
        static::assertFalse($c->contains(2));

        static::assertTrue($c->contains(function ($key, $value) {
            return $value < 5;
        }));
        static::assertFalse($c->contains(function ($key, $value) {
            return $value > 5;
        }));

        $c = new Collection([['v' => 1], ['v' => 3], ['v' => 5]]);

        static::assertTrue($c->contains('v', 1));
        static::assertFalse($c->contains('v', 2));

        $c = new Collection(['date', 'class', (object) ['foo' => 50]]);

        static::assertTrue($c->contains('date'));
        static::assertTrue($c->contains('class'));
        static::assertFalse($c->contains('foo'));
    }

    public function testContainsStrict()
    {
        $c = new Collection([1, 3, 5, '02']);

        static::assertTrue($c->containsStrict(1));
        static::assertFalse($c->containsStrict(2));
        static::assertTrue($c->containsStrict('02'));
        static::assertTrue($c->containsStrict(function ($key, $value) {
            return $value < 5;
        }));
        static::assertFalse($c->containsStrict(function ($key, $value) {
            return $value > 5;
        }));

        $c = new Collection([['v' => 1], ['v' => 3], ['v' => '04'], ['v' => 5]]);

        static::assertTrue($c->containsStrict('v', 1));
        static::assertFalse($c->containsStrict('v', 2));
        static::assertFalse($c->containsStrict('v', 4));
        static::assertTrue($c->containsStrict('v', '04'));

        $c = new Collection(['date', 'class', (object) ['foo' => 50], '']);

        static::assertTrue($c->containsStrict('date'));
        static::assertTrue($c->containsStrict('class'));
        static::assertFalse($c->containsStrict('foo'));
        static::assertFalse($c->containsStrict(null));
        static::assertTrue($c->containsStrict(''));
    }

    public function testGettingSumFromCollection()
    {
        $c = new Collection([(object) ['foo' => 50], (object) ['foo' => 50]]);

        static::assertEquals(100, $c->sum('foo'));

        $c = new Collection([(object) ['foo' => 50], (object) ['foo' => 50]]);

        static::assertEquals(100, $c->sum(function ($i) {
            return $i->foo;
        }));
    }

    public function testCanSumValuesWithoutACallback()
    {
        $c = new Collection([1, 2, 3, 4, 5]);

        static::assertEquals(15, $c->sum());
    }

    public function testGettingSumFromEmptyCollection()
    {
        $c = new Collection();

        static::assertEquals(0, $c->sum('foo'));
    }

    public function testValueRetrieverAcceptsDotNotation()
    {
        $c = new Collection([
            (object) ['id' => 1, 'foo' => ['bar' => 'B']], (object) ['id' => 2, 'foo' => ['bar' => 'A']],
        ]);

        static::assertEquals([2, 1], $c->sortBy('foo.bar')->pluck('id')->all());
    }

    public function testRejectRemovesElementsPassingTruthTest()
    {
        $c = new Collection(['foo', 'bar']);

        static::assertEquals(['foo'], $c->reject('bar')->values()->all());

        $c = new Collection(['foo', 'bar']);

        static::assertEquals(['foo'], $c->reject(function ($v) {
            return $v === 'bar';
        })->values()->all());

        $c = new Collection(['foo', null]);

        static::assertEquals(['foo'], $c->reject(null)->values()->all());

        $c = new Collection(['foo', 'bar']);

        static::assertEquals(['foo', 'bar'], $c->reject('baz')->values()->all());

        $c = new Collection(['foo', 'bar']);

        static::assertEquals(['foo', 'bar'], $c->reject(function ($v) {
            return $v === 'baz';
        })->values()->all());

        $c = new Collection(['id' => 1, 'primary' => 'foo', 'secondary' => 'bar']);

        static::assertEquals(['primary' => 'foo', 'secondary' => 'bar'], $c->reject(function ($item, $key) {
            return $key === 'id';
        })->all());
    }

    public function testSearchReturnsIndexOfFirstFoundItem()
    {
        $c = new Collection([1, 2, 3, 4, 5, 2, 5, 'foo' => 'bar']);

        static::assertEquals(1, $c->search(2));
        static::assertEquals('foo', $c->search('bar'));
        static::assertEquals(4, $c->search(function ($value) {
            return $value > 4;
        }));
        static::assertEquals('foo', $c->search(function ($value) {
            return ! \is_numeric($value);
        }));
    }

    public function testSearchReturnsFalseWhenItemIsNotFound()
    {
        $c = new Collection([1, 2, 3, 4, 5, 'foo' => 'bar']);

        static::assertFalse($c->search(6));
        static::assertFalse($c->search('foo'));
        static::assertFalse($c->search(function ($value) {
            return $value < 1 && \is_numeric($value);
        }));
        static::assertFalse($c->search(function ($value) {
            return $value === 'nope';
        }));
    }

    public function testKeys()
    {
        $c = new Collection(['name' => 'collection', 'framework' => 'narrowspark']);

        static::assertEquals(['name', 'framework'], $c->keys()->all());
    }

    public function testPaginate()
    {
        $c = new Collection(['one', 'two', 'three', 'four']);

        static::assertEquals(['one', 'two'], $c->forPage(1, 2)->all());
        static::assertEquals([2 => 'three', 3 => 'four'], $c->forPage(2, 2)->all());
        static::assertEquals([], $c->forPage(3, 2)->all());
    }

    public function testPrepend()
    {
        $c = new Collection(['one', 'two', 'three', 'four']);

        static::assertEquals(['zero', 'one', 'two', 'three', 'four'], $c->prepend('zero')->all());

        $c = new Collection(['one' => 1, 'two' => 2]);

        static::assertEquals(['zero' => 0, 'one' => 1, 'two' => 2], $c->prepend(0, 'zero')->all());
    }

    public function testZip()
    {
        $c = new Collection([1, 2, 3]);
        $c = $c->zip(new Collection([4, 5, 6]));

        static::assertInstanceOf(Collection::class, $c);
        static::assertInstanceOf(Collection::class, $c[0]);
        static::assertInstanceOf(Collection::class, $c[1]);
        static::assertInstanceOf(Collection::class, $c[2]);
        static::assertCount(3, $c);
        static::assertEquals([1, 4], $c[0]->all());
        static::assertEquals([2, 5], $c[1]->all());
        static::assertEquals([3, 6], $c[2]->all());

        $c = new Collection([1, 2, 3]);
        $c = $c->zip([4, 5, 6], [7, 8, 9]);

        static::assertCount(3, $c);
        static::assertEquals([1, 4, 7], $c[0]->all());
        static::assertEquals([2, 5, 8], $c[1]->all());
        static::assertEquals([3, 6, 9], $c[2]->all());

        $c = new Collection([1, 2, 3]);
        $c = $c->zip([4, 5, 6], [7]);

        static::assertCount(3, $c);
        static::assertEquals([1, 4, 7], $c[0]->all());
        static::assertEquals([2, 5, null], $c[1]->all());
        static::assertEquals([3, 6, null], $c[2]->all());
    }

    public function testGettingMaxItemsFromCollection()
    {
        $c = new Collection([(object) ['foo' => 10], (object) ['foo' => 20]]);

        static::assertEquals(20, $c->max(function ($item) {
            return $item->foo;
        }));
        static::assertEquals(20, $c->max('foo'));

        $c = new Collection([['foo' => 10], ['foo' => 20]]);

        static::assertEquals(20, $c->max('foo'));

        $c = new Collection([1, 2, 3, 4, 5]);

        static::assertEquals(5, $c->max());

        $c = new Collection();

        static::assertNull($c->max());
    }

    public function testGettingMinItemsFromCollection()
    {
        $c = new Collection([(object) ['foo' => 10], (object) ['foo' => 20]]);

        static::assertEquals(10, $c->min(function ($item) {
            return $item->foo;
        }));
        static::assertEquals(10, $c->min('foo'));

        $c = new Collection([['foo' => 10], ['foo' => 20]]);

        static::assertEquals(10, $c->min('foo'));

        $c = new Collection([1, 2, 3, 4, 5]);

        static::assertEquals(1, $c->min());

        $c = new Collection();

        static::assertNull($c->min());
    }

    public function testGettingAvgItemsFromCollection()
    {
        $c = new Collection([(object) ['foo' => 10], (object) ['foo' => 20]]);

        static::assertEquals(15, $c->average(function ($item) {
            return $item->foo;
        }));
        static::assertEquals(15, $c->average('foo'));

        $c = new Collection([['foo' => 10], ['foo' => 20]]);

        static::assertEquals(15, $c->average('foo'));

        $c = new Collection([1, 2, 3, 4, 5]);

        static::assertEquals(3, $c->average());

        $c = new Collection();

        static::assertNull($c->average());
    }

    public function testCombineWithArray()
    {
        $expected = [
            1 => 4,
            2 => 5,
            3 => 6,
        ];

        $c = new Collection(\array_keys($expected));

        $actual = $c->combine(\array_values($expected))->toArray();

        static::assertSame($expected, $actual);
    }

    public function testCombineWithCollection()
    {
        $expected = [
            1 => 4,
            2 => 5,
            3 => 6,
        ];
        $keyCollection   = new Collection(\array_keys($expected));
        $valueCollection = new Collection(\array_values($expected));
        $actual          = $keyCollection->combine($valueCollection)->toArray();

        static::assertSame($expected, $actual);
    }

    public function testReduce()
    {
        $data = new Collection([1, 2, 3]);

        static::assertEquals(6, $data->reduce(function ($carry, $element) {
            return $carry += $element;
        }));
    }

    public function testRandomThrowsAnExceptionUsingAmountBiggerThanCollectionSize()
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = new Collection([1, 2, 3]);
        $data->random(4);
    }

    public function testPipe()
    {
        $c = new Collection([1, 2, 3]);

        static::assertEquals(6, $c->pipe(function ($c) {
            return $c->sum();
        }));
    }

    public function testMedianValueWithArrayCollection()
    {
        $c = new Collection([1, 2, 2, 4]);

        static::assertEquals(2, $c->median());
    }

    public function testMedianValueByKey()
    {
        $c = new Collection([
            (object) ['foo' => 1],
            (object) ['foo' => 2],
            (object) ['foo' => 2],
            (object) ['foo' => 4],
        ]);

        static::assertEquals(2, $c->median('foo'));
    }

    public function testEvenMedianCollection()
    {
        $c = new Collection([
            (object) ['foo' => 0],
            (object) ['foo' => 3],
        ]);

        static::assertEquals(1.5, $c->median('foo'));
    }

    public function testMedianOutOfOrderCollection()
    {
        $c = new Collection([
            (object) ['foo' => 0],
            (object) ['foo' => 5],
            (object) ['foo' => 3],
        ]);

        static::assertEquals(3, $c->median('foo'));
    }

    public function testMedianOnEmptyCollectionReturnsNull()
    {
        $c = new Collection();

        static::assertNull($c->median());
    }

    public function testModeOnNullCollection()
    {
        $c = new Collection();

        static::assertNull($c->mode());
    }

    public function testMode()
    {
        $c = new Collection([1, 2, 3, 4, 4, 5]);

        static::assertEquals([4], $c->mode());
    }

    public function testModeValueByKey()
    {
        $c = new Collection([
            (object) ['foo' => 1],
            (object) ['foo' => 1],
            (object) ['foo' => 2],
            (object) ['foo' => 4],
        ]);

        static::assertEquals([1], $c->mode('foo'));
    }

    public function testWithMultipleModeValues()
    {
        $c = new Collection([1, 2, 2, 1]);

        static::assertEquals([1, 2], $c->mode());
    }

    public function testSliceOffset()
    {
        $c = new Collection([1, 2, 3, 4, 5, 6, 7, 8]);

        static::assertEquals([4, 5, 6, 7, 8], $c->slice(3)->values()->toArray());
    }

    public function testSliceNegativeOffset()
    {
        $c = new Collection([1, 2, 3, 4, 5, 6, 7, 8]);

        static::assertEquals([6, 7, 8], $c->slice(-3)->values()->toArray());
    }

    public function testSliceOffsetAndLength()
    {
        $c = new Collection([1, 2, 3, 4, 5, 6, 7, 8]);

        static::assertEquals([4, 5, 6], $c->slice(3, 3)->values()->toArray());
    }

    public function testSliceOffsetAndNegativeLength()
    {
        $c = new Collection([1, 2, 3, 4, 5, 6, 7, 8]);

        static::assertEquals([4, 5, 6, 7], $c->slice(3, -1)->values()->toArray());
    }

    public function testSliceNegativeOffsetAndLength()
    {
        $c = new Collection([1, 2, 3, 4, 5, 6, 7, 8]);

        static::assertEquals([4, 5, 6], $c->slice(-5, 3)->values()->toArray());
    }

    public function testSliceNegativeOffsetAndNegativeLength()
    {
        $c = new Collection([1, 2, 3, 4, 5, 6, 7, 8]);

        static::assertEquals([3, 4, 5, 6], $c->slice(-6, -2)->values()->toArray());
    }

    public function testCollectonFromTraversable()
    {
        $c = new Collection(new ArrayObject([1, 2, 3]));

        static::assertEquals([1, 2, 3], $c->toArray());
    }

    public function testCollectonFromTraversableWithKeys()
    {
        $c = new Collection(new ArrayObject(['foo' => 1, 'bar' => 2, 'baz' => 3]));

        static::assertEquals(['foo' => 1, 'bar' => 2, 'baz' => 3], $c->toArray());
    }

    public function testSplitCollectionWithADivisableCount()
    {
        $c = new Collection(['a', 'b', 'c', 'd']);

        static::assertEquals(
            [['a', 'b'], ['c', 'd']],
            $c->split(2)->map(function (Collection $chunk) {
                return $chunk->values()->toArray();
            })->toArray()
        );
    }

    public function testSplitCollectionWithAnUndivisableCount()
    {
        $c = new Collection(['a', 'b', 'c']);

        static::assertEquals(
            [['a', 'b'], ['c']],
            $c->split(2)->map(function (Collection $chunk) {
                return $chunk->values()->toArray();
            })->toArray()
        );
    }

    public function testSplitCollectionWithCountLessThenDivisor()
    {
        $c = new Collection(['a']);

        static::assertEquals(
            [['a']],
            $c->split(2)->map(function (Collection $chunk) {
                return $chunk->values()->toArray();
            })->toArray()
        );
    }

    public function testSplitEmptyCollection()
    {
        $c = new Collection();

        static::assertEquals(
            [],
            $c->split(2)->map(function (Collection $chunk) {
                return $chunk->values()->toArray();
            })->toArray()
        );
    }

    public function testArsort()
    {
        $expected = [
            'd' => 3,
            'b' => 2,
            'c' => 2,
            'a' => 1,
        ];

        $c = new Collection([
            'a' => 1,
            'b' => 2,
            'c' => 2,
            'd' => 3,
        ]);

        static::assertSame($expected, $c->arsort()->all());
    }

    public function testArsortNatural()
    {
        $c = new Collection(['a9', 'a1', 'a10']);

        static::assertSame(
            [2 => 'a10', 0 => 'a9', 1 => 'a1'],
            $c->arsort(\SORT_NATURAL)->all()
        );
    }

    public function testArsortRegular()
    {
        $c = new Collection(['a9', 'a1', 'a10']);

        static::assertNotSame(
            [2 => 'a10', 0 => 'a9', 1 => 'a1'],
            $c->arsort(\SORT_REGULAR)->all()
        );
    }

    public function testAsort()
    {
        $c = new Collection([
            'a' => 3,
            'b' => 2,
            'c' => 2,
            'd' => 1,
        ]);

        static::assertSame(
            ['d' => 1, 'b' => 2, 'c' => 2, 'a' => 3],
            $c->asort()->all()
        );
    }

    public function testAsortNatural()
    {
        $c = new Collection(['a9', 'a1', 'a10']);

        static::assertSame([1 => 'a1', 0 => 'a9', 2 => 'a10'], $c->asort(\SORT_NATURAL)->all());
    }

    public function testAsortRegular()
    {
        $c = new Collection(['a9', 'a1', 'a10']);

        static::assertNotSame([1 => 'a1', 0 => 'a9', 2 => 'a10'], $c->asort(\SORT_REGULAR)->all());
    }

    public function testNatcasesort()
    {
        $c = new Collection(['a9', 'a1', 'a10', 'A2']);

        static::assertSame(
            [1 => 'a1', 3 => 'A2', 0 => 'a9', 2 => 'a10'],
            $c->natcasesort()->all()
        );
    }

    public function testNatsort()
    {
        $c = new Collection(['a9', 'a1', 'a10', 'A2']);

        static::assertSame(
            [3 => 'A2', 1 => 'a1', 0 => 'a9', 2 => 'a10'],
            $c->natsort()->all()
        );
    }

    public function testUasort()
    {
        $c = new Collection([
            'a' => ['red', 3],
            'b' => ['green', 2],
            'c' => ['blue', 2],
            'd' => ['yellow', 1],
        ]);

        static::assertSame(
            [
                'd' => ['yellow', 1],
                'b' => ['green', 2],
                'c' => ['blue', 2],
                'a' => ['red', 3],
            ],
            $c->uasort(function ($a, $b) {
                return $a[1] - $b[1];
            })->all()
        );
    }

    public function testUksort()
    {
        $c = new Collection([
            'a3' => 1,
            'b2' => 2,
            'c2' => 3,
            'd1' => 4,
        ]);

        static::assertSame(
            [
                'd1' => 4,
                'b2' => 2,
                'c2' => 3,
                'a3' => 1,
            ],
            $c->uksort(function ($a, $b) {
                return \strcmp(\mb_substr($a, 1), \mb_substr($b, 1));
            })->all()
        );
    }

    public function testUsort()
    {
        $c = new Collection([
            'a' => ['red', 3],
            'b' => ['green', 2],
            'c' => ['blue', 2],
            'd' => ['yellow', 1],
        ]);

        static::assertSame(
            [
                0 => ['yellow', 1],
                1 => ['green', 2],
                2 => ['blue', 2],
                3 => ['red', 3],
            ],
            $c->usort(function ($a, $b) {
                return $a[1] - $b[1];
            })->all()
        );
    }
}
