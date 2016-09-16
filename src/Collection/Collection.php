<?php
declare(strict_types=1);
namespace Narrowspark\Collection;

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use CachingIterator;
use Closure;
use Countable;
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
     * @param callable|Closure|array|Traversable\Iterator|self|IteratorAggregate|JsonSerializable $items
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
     * @param int $depth
     *
     * @return static
     */
    public function flatten(int $depth = INF)
    {
        return new static(Arr::flatten($this->items, $depth));
    }

    /**
     * Flip the items in the collection.
     *
     * @return static
     */
    public function flip()
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
    public function except($keys)
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
    public function filter(callable $callback = null)
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
    public function merge($items)
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Returns a new collection with $value added as last element. If $key is not provided
     * it will be next integer index.
     *
     * @param mixed      $value
     * @param mixed|null $key
     *
     * @return static
     */
    public function append($value, $key = null)
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
    public function values()
    {
        return new static(array_values($this->items));
    }

    /**
     * Collapse the collection of items into a single array.
     *
     * @return static
     */
    public function collapse()
    {
        return new static(Arr::collapse($this->items));
    }

    /**
     * Get the keys of the collection items.
     *
     * @return static
     */
    public function keys()
    {
        return new static(array_keys($this->items));
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
    public function pluck($value, $key = null)
    {
        return new static(Arr::pluck($this->items, $value, $key));
    }

    /**
     * Push an item onto the beginning of the collection.
     *
     * @param mixed $value
     * @param mixed $key
     *
     * @return $this
     */
    public function prepend($value, $key = null)
    {
        $this->items = Arr::prepend($this->items, $value, $key);

        return $this;
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
     * Get the items with the specified keys.
     *
     * @param mixed $keys
     *
     * @return static
     */
    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(Arr::only($this->items, $keys));
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
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
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
     * Results array of items from Collection or Arrayable.
     *
     * @param callable|Closure|array|Traversable\Iterator|self|IteratorAggregate|JsonSerializable $items
     *
     * @return array
     */
    protected function getArrayableItems($items)
    {
        if (is_array($items)) {
            return $items;
        } elseif (is_callable($items)) {
            return call_user_func($items);
        } elseif ($items instanceof self) {
            return $items->all();
        } elseif ($items instanceof IteratorAggregate) {
            return $items->getIterator();
        } elseif ($items instanceof Iterator) {
            return $items;
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        } elseif ($items instanceof JsonSerializable) {
            return $items->jsonSerialize();
        } elseif (method_exists($items, 'toJson')) {
            return json_decode($items->toJson(), true);
        } elseif (method_exists($items, 'toArray')) {
            return $items->toArray();
        }

        return (array) $items;
    }
}
