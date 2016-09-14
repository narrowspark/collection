<?php
declare(strict_types=1);
namespace Narrowspark\Collection\Tests\Fixture;

class TestArrayableObjectFixture
{
    public function toArray(): array
    {
        return ['foo' => 'bar'];
    }
}
