<?php
declare(strict_types=1);
namespace Narrowspark\Collection;

class LoopOneHundredThousandItems
{
    /**
     * @Revs(10)
     */
    public function benchCollectionArray()
    {
        $collection = Collection::from(\range(0, 100000));
        $collection->each(function ($i) {
        });
    }

    public function benchForeachArray()
    {
        $array = \range(0, 100000);

        foreach ($array as $i) {
        }
    }
}
