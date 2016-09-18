<?php
declare(strict_types=1);
namespace Narrowspark\Collection;

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use CachingIterator;
use Closure;
use Countable;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use JsonSerializable;
use Narrowspark\Arr\Arr;
use Serializable;
use Traversable;

class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable, Serializable
{
    /**
     * The registered string extensions.
     *
     * @var array
     */
    protected static $extensions = [];

    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected $items = [];

    /**
     * Create a new collection.
     *
     * @param callable|\Closure|array|Traversable\Iterator|self|IteratorAggregate|JsonSerializable $items
     */
    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        if (! static::hasExtensions($method)) {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        if (static::$extensions[$method] instanceof Closure) {
            return call_user_func_array(Closure::bind(static::$extensions[$method], null, static::class), $parameters);
        }

        return call_user_func_array(static::$extensions[$method], $parameters);
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (! static::hasExtensions($method)) {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        if (static::$extensions[$method] instanceof Closure) {
            return call_user_func_array(static::$extensions[$method]->bindTo($this, static::class), $parameters);
        }

        return call_user_func_array(static::$extensions[$method], $parameters);
    }

    /**
     * Static alias of normal constructor.
     *
     * @param callable|\Closure|array|Traversable\Iterator|self|IteratorAggregate|JsonSerializable $items
     *
     * @return $this
     */
    public static function from($items = []): Collection
    {
        return new self($items);
    }

    /**
     * Get the average value of a given key.
     *
     * @param callable|string|null $callback
     *
     * @return mixed
     */
    public function average($callback = null)
    {
        if ($count = $this->count()) {
            return $this->sum($callback) / $count;
        }
    }

    /**
     * Get the median of a given key.
     *
     * @param null $key
     *
     * @return mixed|null
     */
    public function median($key = null)
    {
        $count = $this->count();

        if ($count == 0) {
            return;
        }

        $collection = isset($key) ? $this->pluck($key) : $this;
        $values = $collection->sort()->values();

        $middle = (int) ($count / 2);

        if ($count % 2) {
            return $values->get($middle);
        }

        return (new static([
            $values->get($middle - 1), $values->get($middle),
        ]))->average();
    }

    /**
     * Get the mode of a given key.
     *
     * @param string|int|null $key
     *
     * @return array|null
     */
    public function mode($key = null)
    {
        $count = $this->count();

        if ($count == 0) {
            return;
        }

        $collection = isset($key) ? $this->pluck($key) : $this;
        $counts = new self();

        $collection->each(function ($value) use ($counts) {
            $counts[$value] = isset($counts[$value]) ? $counts[$value] + 1 : 1;
        });

        $sorted = $counts->sort();
        $highestValue = $sorted->last();

        return $sorted->filter(function ($value) use ($highestValue) {
            return $value == $highestValue;
        })->sort()->keys()->all();
    }

    /**
     * Get the first item from the collection.
     *
     * @param callable|null $callback
     * @param mixed         $default
     *
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        return Arr::first($this->items, $callback, $default);
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @param int|float|string $depth
     *
     * @return static
     */
    public function flatten($depth = INF): Collection
    {
        return new static(self::flattenCallback($this->items, $depth));
    }

    /**
     * Flip the items in the collection.
     *
     * @return static
     */
    public function flip(): Collection
    {
        return new static(array_flip($this->items));
    }

    /**
     * Get all items except for those with the specified keys.
     *
     * @param mixed $keys
     *
     * @return static
     */
    public function except($keys): Collection
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(Arr::except($this->items, $keys));
    }

    /**
     * Run a filter over each of the items.
     *
     * @param callable|null $callback
     *
     * @return static
     */
    public function filter(callable $callback = null): Collection
    {
        if ($callback) {
            return new static(Arr::where($this->items, $callback));
        }

        return new static(array_filter($this->items));
    }

    /**
     * Get all of the items in the collection.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Merge the collection with the given items.
     *
     * @param callable|Closure|array|Traversable\Iterator|self|IteratorAggregate|JsonSerializable $items
     *
     * @return static
     */
    public function merge($items): Collection
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Returns a new collection with $value added as last element. If $key is not provided
     * it will be next integer index.
     *
     * @param mixed $value
     * @param mixed $key
     *
     * @return static
     */
    public function append($value, $key = null): Collection
    {
        $items = $this->items;

        if ($key === null) {
            $items[] = $value;
        } else {
            $items[$key] = $value;
        }

        return new static($items);
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return static
     */
    public function values(): Collection
    {
        return new static(array_values($this->items));
    }

    /**
     * Determine if an item exists in the collection.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return bool
     */
    public function contains($key, $value = null): bool
    {
        if (func_num_args() == 2) {
            return $this->contains(function ($item) use ($key, $value) {
                return self::dataGet($item, $key) == $value;
            });
        }

        if ($this->useAsCallable($key)) {
            return ! is_null($this->first($key));
        }

        return in_array($key, $this->items);
    }

    /**
     * Determine if an item exists in the collection using strict comparison.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return bool
     */
    public function containsStrict($key, $value = null): bool
    {
        if (func_num_args() == 2) {
            return $this->contains(function ($item) use ($key, $value) {
                return self::dataGet($item, $key) === $value;
            });
        }

        if ($this->useAsCallable($key)) {
            return ! is_null($this->first($key));
        }

        return in_array($key, $this->items, true);
    }

    /**
     * Get the items in the collection that are not present in the given items.
     *
     * @param mixed $items
     *
     * @return static
     */
    public function diff($items): Collection
    {
        return new static(array_diff($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     *
     * @param mixed $items
     *
     * @return static
     */
    public function diffKeys($items): Collection
    {
        return new static(array_diff_key($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Execute a callback over each item.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function each(callable $callback): Collection
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Create a new collection consisting of every n-th element.
     *
     * @param int $step
     * @param int $offset
     *
     * @return static
     */
    public function every($step, $offset = 0): Collection
    {
        $new = [];
        $position = 0;

        foreach ($this->items as $item) {
            if ($position % $step === $offset) {
                $new[] = $item;
            }

            ++$position;
        }

        return new static($new);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string $key
     * @param mixed  $operator
     * @param mixed  $value
     *
     * @return static
     */
    public function where(string $key, $operator, $value = null): Collection
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->filter($this->operatorForWhere($key, $operator, $value));
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return static
     */
    public function whereStrict(string $key, $value): Collection
    {
        return $this->where($key, '===', $value);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string $key
     * @param mixed  $values
     * @param bool   $strict
     *
     * @return static
     */
    public function whereIn(string $key, $values, bool $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->filter(function ($itemKey, $item) use ($key, $values, $strict) {
            return in_array(self::dataGet($item, $key), $values, $strict);
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string $key
     * @param mixed  $values
     *
     * @return static
     */
    public function whereInStrict(string $key, $values): Collection
    {
        return $this->whereIn($key, $values, true);
    }

    /**
     * Remove an item from the collection by key.
     *
     * @param string|array $keys
     *
     * @return $this
     */
    public function forget($keys): Collection
    {
        foreach ((array) $keys as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    /**
     * Get an item from the collection by key.
     *
     * @param mixed $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this->items[$key];
        }

        return value($default);
    }

    /**
     * Group an associative array by a field or using a callback.
     *
     * @param callable|string $groupBy
     * @param bool            $preserveKeys
     *
     * @return static
     */
    public function groupBy($groupBy, bool $preserveKeys = false): Collection
    {
        $groupBy = $this->valueRetriever($groupBy);
        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKeys = $groupBy($value, $key);

            if (! is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }

            foreach ($groupKeys as $groupKey) {
                if (! array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = new static();
                }

                $results[$groupKey]->offsetSet($preserveKeys ? $key : null, $value);
            }
        }

        return new static($results);
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param callable|string $keyBy
     *
     * @return static
     */
    public function keyBy($keyBy): Collection
    {
        $keyBy = $this->valueRetriever($keyBy);
        $results = [];
        foreach ($this->items as $key => $item) {
            $results[$keyBy($item, $key)] = $item;
        }

        return new static($results);
    }

    /**
     * Determine if an item exists in the collection by key.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function has($key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Concatenate values of a given key as a string.
     *
     * @param string $value
     * @param string $glue
     *
     * @return string
     */
    public function implode(string $value, string $glue = null): string
    {
        $first = $this->first();

        if (is_array($first) || is_object($first)) {
            return implode($glue, $this->pluck($value)->all());
        }

        return implode($value, $this->items);
    }

    /**
     * Intersect the collection with the given items.
     *
     * @param mixed $items
     *
     * @return static
     */
    public function intersect($items): Collection
    {
        return new static(array_intersect($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Get the last item from the collection.
     *
     * @param callable|null $callback
     * @param mixed         $default
     *
     * @return mixed
     */
    public function last(callable $callback = null, $default = null)
    {
        return Arr::last($this->items, $callback, $default);
    }

    /**
     * Get the values of a given key.
     *
     * @param string      $value
     * @param string|null $key
     *
     * @return static
     */
    public function pluck(string $value, $key = null): Collection
    {
        $results = [];

        list($value, $key) = static::explodePluckParameters($value, $key);

        foreach ($this->items as $item) {
            $itemValue = self::dataGet($item, $value);

            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = self::dataGet($item, $key);
                $results[$itemKey] = $itemValue;
            }
        }

        return new static($results);
    }

    /**
     * Run a map over each of the items.
     *
     * @param callable $callback
     *
     * @return static
     */
    public function map(callable $callback): Collection
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param callable $callback
     *
     * @return static
     */
    public function mapWithKeys(callable $callback): Collection
    {
        return $this->flatMap($callback);
    }

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @param callable $callback
     *
     * @return static
     */
    public function flatMap(callable $callback): Collection
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Get the max value of a given key.
     *
     * @param callable|string|null $callback
     *
     * @return mixed
     */
    public function max($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        return $this->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);

            return is_null($result) || $value > $result ? $value : $result;
        });
    }

    /**
     * Create a collection by using this collection for keys and another for its values.
     *
     * @param mixed $values
     *
     * @return static
     */
    public function combine($values): Collection
    {
        return new static(array_combine($this->all(), $this->getArrayableItems($values)));
    }

    /**
     * Union the collection with the given items.
     *
     * @param mixed $items
     *
     * @return static
     */
    public function union($items): Collection
    {
        return new static($this->items + $this->getArrayableItems($items));
    }

    /**
     * Get the min value of a given key.
     *
     * @param callable|string|null $callback
     *
     * @return mixed
     */
    public function min($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        return $this->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);

            return is_null($result) || $value < $result ? $value : $result;
        });
    }

    /**
     * Get the items with the specified keys.
     *
     * @param mixed $keys
     *
     * @return static
     */
    public function only($keys): Collection
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(Arr::only($this->items, $keys));
    }

    /**
     * "Paginate" the collection by slicing it into a smaller collection.
     *
     * @param int $page
     * @param int $perPage
     *
     * @return static
     */
    public function forPage(int $page, int $perPage): Collection
    {
        return $this->slice(($page - 1) * $perPage, $perPage);
    }

    /**
     * Pass the collection to the given callback and return the result.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    public function pipe(callable $callback)
    {
        return $callback($this);
    }

    /**
     * Get and remove the last item from the collection.
     *
     * @return mixed
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * Push an item onto the beginning of the collection.
     *
     * @param mixed $value
     * @param mixed $key
     *
     * @return $this
     */
    public function prepend($value, $key = null): Collection
    {
        $this->items = Arr::prepend($this->items, $value, $key);

        return $this;
    }

    /**
     * Push an item onto the end of the collection.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function push($value): Collection
    {
        $this->offsetSet(null, $value);

        return $this;
    }

    /**
     * Get and remove an item from the collection.
     *
     * @param mixed $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        return Arr::pull($this->items, $key, $default);
    }

    /**
     * Put an item in the collection by key.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return $this
     */
    public function put($key, $value): Collection
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * Get one or more items randomly from the collection.
     *
     * @param int $amount
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public function random($amount = 1)
    {
        if ($amount > ($count = $this->count())) {
            throw new InvalidArgumentException(
                sprintf('You requested %s items, but there are only %s items in the collection', $amount, $count)
            );
        }

        $keys = array_rand($this->items, $amount);

        if ($amount == 1) {
            return $this->items[$keys];
        }

        return new static(array_intersect_key($this->items, array_flip($keys)));
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param callable $callback
     * @param mixed    $initial
     *
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param mixed $callback
     *
     * @return static
     */
    public function reject($callback): Collection
    {
        if ($this->useAsCallable($callback)) {
            return $this->filter(function ($value, $key) use ($callback) {
                return ! $callback($value, $key);
            });
        }

        return $this->filter(function ($item) use ($callback) {
            return $item != $callback;
        });
    }

    /**
     * Reverse items order.
     *
     * @return static
     */
    public function reverse(): Collection
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     *
     * @param mixed $value
     * @param bool  $strict
     *
     * @return mixed
     */
    public function search($value, $strict = false)
    {
        if (! $this->useAsCallable($value)) {
            return array_search($value, $this->items, $strict);
        }
        foreach ($this->items as $key => $item) {
            if (call_user_func($value, $item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Get and remove the first item from the collection.
     *
     * @return mixed
     */
    public function shift()
    {
        return array_shift($this->items);
    }

    /**
     * Shuffle the items in the collection.
     *
     * @param int $seed
     *
     * @return static
     */
    public function shuffle(int $seed = null): Collection
    {
        $items = $this->items;
        if (is_null($seed)) {
            shuffle($items);
        } else {
            srand($seed);
            usort($items, function () {
                return rand(-1, 1);
            });
        }

        return new static($items);
    }

    /**
     * Slice the underlying collection array.
     *
     * @param int $offset
     * @param int $length
     *
     * @return static
     */
    public function slice(int $offset, int $length = null): Collection
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Split a collection into a certain number of groups.
     *
     * @param int $numberOfGroups
     *
     * @return static
     */
    public function split(int $numberOfGroups): Collection
    {
        if ($this->isEmpty()) {
            return new static();
        }

        $groupSize = ceil($this->count() / $numberOfGroups);

        return $this->chunk($groupSize);
    }

    /**
     * Chunk the underlying collection array.
     *
     * @param int|float $size
     *
     * @return static
     */
    public function chunk($size): Collection
    {
        $chunks = [];

        foreach (array_chunk($this->items, (int) $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Sort through each item with a callback.
     *
     * @see http://stackoverflow.com/questions/4353739/preserve-key-order-stable-sort-when-sorting-with-phps-uasort
     * @see http://en.wikipedia.org/wiki/Schwartzian_transform
     *
     * @param callable|null $callback
     *
     * @return static
     */
    public function sort(callable $callback = null): Collection
    {
        $items = $this->items;

        $callback
            ? uasort($items, $callback)
            : asort($items);

        return new static($items);
    }

    /**
     * Sort an array in reverse order and maintain index association.
     *
     * @param int $option
     *
     * @return static
     */
    public function arsort(int $option = SORT_REGULAR): Collection
    {
        $index = 0;
        $items = $this->items;

        foreach ($items as &$item) {
            $item = [$index++, $item];
        }

        uasort($items, function ($a, $b) use ($option) {
            if ($a[1] === $b[1]) {
                return $a[0] - $b[0];
            }

            $set = [-1 => $b[1], 1 => $a[1]];

            asort($set, $option);
            reset($set);

            return key($set);
        });

        foreach ($items as &$item) {
            $item = $item[1];
        }

        return new static($items);
    }

    /**
     * Sort an array and maintain index association.
     *
     * @param int $option
     *
     * @return static
     */
    public function asort(int $option = SORT_REGULAR): Collection
    {
        $index = 0;
        $items = $this->items;

        foreach ($items as &$item) {
            $item = [$index++, $item];
        }

        uasort($items, function ($a, $b) use ($option) {
            if ($a[1] === $b[1]) {
                return $a[0] - $b[0];
            }

            $set = [-1 => $a[1], 1 => $b[1]];

            asort($set, $option);
            reset($set);

            return key($set);
        });

        foreach ($items as &$item) {
            $item = $item[1];
        }

        return new static($items);
    }

    /**
     * Sort an array using a case insensitive "natural order" algorithm.
     *
     * @return static
     */
    public function natcasesort(): Collection
    {
        $index = 0;
        $items = $this->items;

        foreach ($items as &$item) {
            $item = [$index++, $item];
        }

        uasort($items, function ($a, $b) {
            $result = strnatcasecmp($a[1], $b[1]);

            return $result === 0 ? $a[0] - $b[0] : $result;
        });

        foreach ($items as &$item) {
            $item = $item[1];
        }

        return new static($items);
    }

    /**
     * Sort an array using a "natural order" algorithm.
     *
     * @return static
     */
    public function natsort(): Collection
    {
        $index = 0;
        $items = $this->items;

        foreach ($items as &$item) {
            $item = [$index++, $item];
        }

        uasort($items, function ($a, $b) {
            $result = strnatcmp($a[1], $b[1]);

            return $result === 0 ? $a[0] - $b[0] : $result;
        });

        foreach ($items as &$item) {
            $item = $item[1];
        }

        return new static($items);
    }

    /**
     * Sort an array with a user-defined comparison function and maintain index association.
     *
     * @param callable $callback
     *
     * @return static
     */
    public function uasort(callable $callback): Collection
    {
        $index = 0;
        $items = $this->items;

        foreach ($items as &$item) {
            $item = [$index++, $item];
        }

        uasort($items, function ($a, $b) use ($callback) {
            $result = call_user_func($callback, $a[1], $b[1]);

            return $result === 0 ? $a[0] - $b[0] : $result;
        });

        foreach ($items as &$item) {
            $item = $item[1];
        }

        return new static($items);
    }

    /**
     * Sort an array by keys using a user-defined comparison function.
     *
     * @param callable $callback
     *
     * @return static
     */
    public function uksort(callable $callback): Collection
    {
        $items = $this->items;
        $keys = array_combine(array_keys($items), range(1, count($items)));

        uksort($items, function ($a, $b) use ($callback, $keys) {
            $result = call_user_func($callback, $a, $b);

            return $result === 0 ? $keys[$a] - $keys[$b] : $result;
        });

        return new static($items);
    }

    /**
     * Sort an array by values using a user-defined comparison function.
     *
     * @param callable $callback
     *
     * @return static
     */
    public function usort(callable $callback): Collection
    {
        $index = 0;
        $items = $this->items;

        foreach ($items as &$item) {
            $item = [$index++, $item];
        }

        usort($items, function ($a, $b) use ($callback) {
            $result = call_user_func($callback, $a[1], $b[1]);

            return $result === 0 ? $a[0] - $b[0] : $result;
        });

        foreach ($items as &$item) {
            $item = $item[1];
        }

        return new static($items);
    }

    /**
     * Sort the collection using the given callback.
     *
     * @param callable|string $callback
     * @param int             $options
     * @param bool            $descending
     *
     * @return static
     */
    public function sortBy($callback, int $options = SORT_REGULAR, bool $descending = false): Collection
    {
        $results = [];
        $callback = $this->valueRetriever($callback);

        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @param callable|string $callback
     * @param int             $options
     *
     * @return static
     */
    public function sortByDesc($callback, int $options = SORT_REGULAR): Collection
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Splice a portion of the underlying collection array.
     *
     * @param int      $offset
     * @param int|null $length
     * @param mixed    $replacement
     *
     * @return static
     */
    public function splice(int $offset, $length = null, $replacement = []): Collection
    {
        if (func_num_args() == 1) {
            return new static(array_splice($this->items, $offset));
        }

        return new static(array_splice($this->items, $offset, $length, $replacement));
    }

    /**
     * Get the sum of the given values.
     *
     * @param callable|string|null $callback
     *
     * @return mixed
     */
    public function sum($callback = null)
    {
        if (is_null($callback)) {
            return array_sum($this->items);
        }
        $callback = $this->valueRetriever($callback);

        return $this->reduce(function ($result, $item) use ($callback) {
            return $result + $callback($item);
        }, 0);
    }

    /**
     * Take the first or last {$limit} items.
     *
     * @param int $limit
     *
     * @return static
     */
    public function take(int $limit): Collection
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Transform each item in the collection using a callback.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function transform(callable $callback): Collection
    {
        $this->items = $this->map($callback)->all();

        return $this;
    }

    /**
     * Return only unique items from the collection array.
     *
     * @param string|callable|null $key
     * @param bool                 $strict
     *
     * @return static
     */
    public function unique($key = null, bool $strict = false): Collection
    {
        if (is_null($key)) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $key = $this->valueRetriever($key);
        $exists = [];

        return $this->reject(function ($item) use ($key, $strict, &$exists) {
            if (in_array($id = $key($item), $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    /**
     * Return only unique items from the collection array using strict comparison.
     *
     * @param string|callable|null $key
     *
     * @return static
     */
    public function uniqueStrict($key = null): Collection
    {
        return $this->unique($key, true);
    }

    /**
     * Zip the collection together with one or more arrays.
     *
     * e.g. new Collection([1, 2, 3])->zip([4, 5, 6]);
     *      => [[1, 4], [2, 5], [3, 6]]
     *
     * @param  mixed ...$items
     *
     * @return static
     */
    public function zip($items): Collection
    {
        $arrayableItems = array_map(function ($items) {
            return $this->getArrayableItems($items);
        }, func_get_args());
        $params = array_merge([function () {
            return new static(func_get_args());
        }, $this->items], $arrayableItems);

        return new static(call_user_func_array('array_map', $params));
    }

    /**
     * Collapse the collection of items into a single array.
     *
     * @return static
     */
    public function collapse(): Collection
    {
        $results = [];

        foreach ($this->items as $values) {
            if ($values instanceof self) {
                $values = $values->all();
            } elseif (! is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        return new static($results);
    }

    /**
     * Get the keys of the collection items.
     *
     * @return static
     */
    public function keys(): Collection
    {
        return new static(array_keys($this->items));
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_map(function ($value) {
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }

            return $value;
        }, $this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } elseif (method_exists($value, 'toJson')) {
                return json_decode($value->toJson(), true);
            } elseif (method_exists($value, 'toArray')) {
                return $value->toArray();
            }

            return $value;
        }, $this->items);
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     *
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Get a CachingIterator instance.
     *
     * @param int $flags
     *
     * @return \CachingIterator
     */
    public function getCachingIterator(int $flags = CachingIterator::CALL_TOSTRING): CachingIterator
    {
        return new CachingIterator($this->getIterator(), $flags);
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize($this->toJson());
    }

    /**
     * Unserialize a string to a collection object.
     *
     * @param string $serialized
     *
     * @return static
     */
    public function unserialize($serialized)
    {
        return new static(unserialize($serialized));
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Get an item at a given offset.
     *
     * @param mixed $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items[$key];
    }

    /**
     * Set the item at a given offset.
     *
     * @param mixed $key
     * @param mixed $value
     */
    public function offsetSet($key, $value)
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param string $key
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    /**
     * Register a custom extensions.
     *
     * @param string   $name
     * @param callable $callback
     */
    public static function extend(string $name, callable $callback)
    {
        static::$extensions[$name] = $callback;
    }

    /**
     * Checks if extensions is registered.
     *
     * @param string $name
     *
     * @return bool
     */
    public static function hasExtensions(string $name): bool
    {
        return isset(static::$extensions[$name]);
    }

    /**
     * Explode the "value" and "key" arguments passed to "pluck".
     *
     * @param string|array      $value
     * @param string|array|null $key
     *
     * @return array
     */
    protected static function explodePluckParameters($value, $key): array
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        return [$value, $key];
    }

    /**
     * Get an operator checker callback.
     *
     * @param string $key
     * @param string $operator
     * @param mixed  $value
     *
     * @return \Closure
     */
    protected function operatorForWhere(string $key, $operator, $value)
    {
        return function ($itemKey, $item) use ($key, $operator, $value) {
            $retrieved = self::dataGet($item, $key);

            switch ($operator) {
                default:
                case '=':
                case '==':
                    return $retrieved == $value;
                case '!=':
                case '<>':
                    return $retrieved != $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
            }
        };
    }

    /**
     * Get a value retrieving callback.
     *
     * @param mixed $value
     *
     * @return callable
     */
    protected function valueRetriever($value)
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            return self::dataGet($item, $value);
        };
    }

    /**
     * Results array of items from Collection or Arrayable.
     *
     * @param callable|Closure|array|Traversable\Iterator|self|IteratorAggregate|JsonSerializable $items
     *
     * @return array
     */
    protected function getArrayableItems($items): array
    {
        if (is_array($items)) {
            return $items;
        } elseif (is_callable($items)) {
            return call_user_func($items);
        } elseif ($items instanceof self) {
            return $items->all();
        } elseif ($items instanceof Iterator) {
            return $items;
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        } elseif ($items instanceof IteratorAggregate) {
            return $items->getIterator();
        } elseif ($items instanceof JsonSerializable) {
            return $items->jsonSerialize();
        } elseif (method_exists($items, 'toJson')) {
            return json_decode($items->toJson(), true);
        } elseif (method_exists($items, 'toArray')) {
            return $items->toArray();
        }

        return (array) $items;
    }

    /**
     * Determine if the given value is callable, but not a string.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function useAsCallable($value): bool
    {
        return ! is_string($value) && is_callable($value);
    }

    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param mixed        $target
     * @param string|array $key
     * @param mixed        $default
     *
     * @return mixed
     */
    protected static function dataGet($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        while (($segment = array_shift($key)) !== null) {
            if ($segment === '*') {
                if ($target instanceof self) {
                    $target = $target->all();
                } elseif (! is_array($target)) {
                    return Arr::value($default);
                }

                $result = Arr::pluck($target, $key);

                return in_array('*', $key) ? Arr::collapse($result) : $result;
            }

            if (Arr::accessible($target) && Arr::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return Arr::value($default);
            }
        }

        return $target;
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param array|\Collection $array
     * @param int|float|string  $depth
     *
     * @return array
     */
    private function flattenCallback($array, $depth = INF): array
    {
        $depth = (int) $depth;

        return array_reduce($array, function ($result, $item) use ($depth) {
            $item = $item instanceof self ? $item->all() : $item;

            if (! is_array($item)) {
                return array_merge($result, [$item]);
            } elseif ($depth === 1) {
                return array_merge($result, array_values($item));
            } else {
                return array_merge($result, self::flattenCallback($item, $depth - 1));
            }
        }, []);
    }
}
