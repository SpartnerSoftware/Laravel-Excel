<?php

namespace Maatwebsite\Excel\Cache;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Psr\SimpleCache\CacheInterface;

class MemoryCacheDeprecated implements CacheInterface
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
    public function clear()
    {
        $this->cache = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        unset($this->cache[$key]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        if ($this->has($key)) {
            return $this->cache[$key];
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
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
    public function has($key)
    {
        return isset($this->cache[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $this->cache[$key] = $value;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
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
