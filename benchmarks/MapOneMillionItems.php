<?php
declare(strict_types=1);
namespace Narrowspark\Collection;

class MapOneMillionItems
{
    const ONE_MILLION = 1000000;

    /**
     * @Revs(10)
     */
    public function benchCollectionArray()
    {
        $collection = Collection::from(range(0, static::ONE_MILLION));

        $result = $collection->map(function ($i) {
            return $i * 2;
        });
    }

    /**
     * @Revs(10)
     */
    public function benchNativeArrayMap()
    {
        $result = array_map(function ($i) {
            return $i * 2;
        }, range(0, static::ONE_MILLION));
    }

    /**
     * @Revs(10)
     */
    public function benchForEachArray()
    {
        $items = range(0, static::ONE_MILLION);
        foreach ($items as $i => $item) {
            $items[$i] = $item * 2;
        }
    }
}
