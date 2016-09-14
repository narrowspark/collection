<?php
declare(strict_types=1);
namespace Narrowspark\Collection\Tests\Fixture;

class TestJsonableObjectFixture
{
    public function toJson(int $options = 0): string
    {
        return '{"foo":"bar"}';
    }
}
