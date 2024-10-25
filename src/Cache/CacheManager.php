<?php

namespace Maatwebsite\Excel\Cache;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Manager;
use Psr\SimpleCache\CacheInterface;

class CacheManager extends Manager
{
    /**
     * @const string
     */
    public const DRIVER_BATCH = 'batch';

    /**
     * @const string
     */
    public const DRIVER_MEMORY = 'memory';

    /**
     * @const string
     */
    public const DRIVER_ILLUMINATE = 'illuminate';

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return config('excel.cache.driver', 'memory');
    }

    /**
     * @return MemoryCache
     */
    public function createMemoryDriver(): CacheInterface
    {
        if (!InstalledVersions::satisfies(new VersionParser, 'psr/simple-cache', '^3.0')) {
            return new MemoryCacheDeprecated(
                config('excel.cache.batch.memory_limit', 60000)
            );
        }

        return new MemoryCache(
            config('excel.cache.batch.memory_limit', 60000)
        );
    }

    /**
     * @return BatchCache
     */
    public function createBatchDriver(): CacheInterface
    {
        if (!InstalledVersions::satisfies(new VersionParser, 'psr/simple-cache', '^3.0')) {
            return new BatchCacheDeprecated(
                $this->createIlluminateDriver(),
                $this->createMemoryDriver(),
                config('excel.cache.default_ttl')
            );
        }

        return new BatchCache(
            $this->createIlluminateDriver(),
            $this->createMemoryDriver(),
            config('excel.cache.default_ttl')
        );
    }

    /**
     * @return CacheInterface
     */
    public function createIlluminateDriver(): CacheInterface
    {
        return Cache::driver(
            config('excel.cache.illuminate.store')
        );
    }

    public function flush()
    {
        $this->driver()->clear();
    }

    public function isInMemory(): bool
    {
        return $this->getDefaultDriver() === self::DRIVER_MEMORY;
    }
}
