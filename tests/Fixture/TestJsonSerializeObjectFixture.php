<?php
declare(strict_types=1);
namespace Narrowspark\Collection\Tests\Fixture;

use JsonSerializable;

class TestJsonSerializeObjectFixture implements JsonSerializable
{
    public function jsonSerialize()
    {
        return ['foo' => 'bar'];
    }
}
