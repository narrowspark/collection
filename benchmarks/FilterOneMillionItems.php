<?php
declare(strict_types=1);
namespace Narrowspark\Collection;

class FilterOneMillionItems
{
    const ONE_MILLION = 1000000;

    /**
     * @Revs(10)
     */
    public function benchCollectionArray()
    {
        $collection = Collection::from(range(0, static::ONE_MILLION));

        $result = $collection->filter(function ($i) {
            return $i % 2 === 0;
        });
    }

    /**
     * @Revs(10)
     */
    public function benchNativeArrayFilter()
    {
        $result = array_filter(range(0, static::ONE_MILLION), function ($i) {
            return $i % 2 === 0;
        });
    }

    /**
     * @Revs(10)
     */
    public function benchForEachArray()
    {
        $items = range(0, static::ONE_MILLION);
        $filtered = [];
        foreach ($items as $i => $item) {
            if ($i % 2 === 0) {
                $filtered[$i] = $item;
            }
        }
    }
}
