<?php

namespace Maatwebsite\Excel\Cache;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Psr\SimpleCache\CacheInterface;

class MemoryCache implements CacheInterface
{
    /**
     * @var int|null
     */
    protected $memoryLimit;

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * @param  int|null  $memoryLimit
     */
    public function __construct(?int $memoryLimit = null)
    {
        $this->memoryLimit = $memoryLimit;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->cache = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        unset($this->cache[$key]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            return $this->cache[$key];
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key): bool
    {
        return isset($this->cache[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->cache[$key] = $value;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function reachedMemoryLimit(): bool
    {
        // When no limit is given, we'll never reach any limit.
        if (null === $this->memoryLimit) {
            return false;
        }

        return count($this->cache) >= $this->memoryLimit;
    }

    /**
     * @return array
     */
    public function flush(): array
    {
        $memory = $this->cache;

        foreach ($memory as $cell) {
            if ($cell instanceof Cell) {
                $cell->detach();
            }
        }

        $this->clear();

        return $memory;
    }
}
