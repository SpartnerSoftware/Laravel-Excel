<?php

namespace Maatwebsite\Excel\Tests\Cache;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use DateInterval;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Event;
use Maatwebsite\Excel\Cache\BatchCache;
use Maatwebsite\Excel\Cache\BatchCacheDeprecated;
use Maatwebsite\Excel\Cache\CacheManager;
use Maatwebsite\Excel\Cache\MemoryCache;
use Maatwebsite\Excel\Tests\TestCase;
use Psr\SimpleCache\CacheInterface;

class BatchCacheTest extends TestCase
{
    /**
     * @var Repository
     */
    private $cache;

    /**
     * @var MemoryCache
     */
    private $memory;

    public function test_will_get_multiple_from_memory_if_cells_hold_in_memory()
    {
        $inMemory = [
            'A1' => 'A1-value',
            'A2' => 'A2-value',
            'A3' => 'A3-value',
        ];

        $cache = $this->givenCache($inMemory);

        $this->assertEquals(
            $inMemory,
            $cache->getMultiple(['A1', 'A2', 'A3'])
        );

        $this->assertEquals('A3-value', $cache->get('A3'));
    }

    public function test_will_get_multiple_from_cache_if_cells_are_persisted()
    {
        $inMemory  = [];
        $persisted = [
            'A1' => 'A1-value',
            'A2' => 'A2-value',
            'A3' => 'A3-value',
        ];

        $cache = $this->givenCache($inMemory, $persisted);

        $this->assertEquals(
            $persisted,
            $cache->getMultiple(['A1', 'A2', 'A3'])
        );

        $this->assertEquals('A3-value', $cache->get('A3'));
    }

    public function test_will_get_multiple_from_cache_and_persisted()
    {
        $inMemory  = [
            'A1' => 'A1-value',
            'A2' => 'A2-value',
            'A3' => 'A3-value',
        ];
        $persisted = [
            'A4' => 'A4-value',
            'A5' => 'A5-value',
            'A6' => 'A6-value',
        ];

        $cache = $this->givenCache($inMemory, $persisted);

        $this->assertEquals(
            array_merge($inMemory, $persisted),
            $cache->getMultiple(['A1', 'A2', 'A3', 'A4', 'A5', 'A6'])
        );

        $this->assertEquals('A3-value', $cache->get('A3'));
        $this->assertEquals('A6-value', $cache->get('A6'));
    }

    public function test_it_persists_to_cache_when_memory_limit_reached_on_setting_a_value()
    {
        $memoryLimit = 3;
        $persisted   = [];
        $inMemory    = [
            'A1' => 'A1-value',
            'A2' => 'A2-value',
            'A3' => 'A3-value',
        ];

        $cache = $this->givenCache($inMemory, $persisted, $memoryLimit);

        // Setting a 4th value will reach the memory limit
        $cache->set('A4', 'A4-value', 10000);

        // Nothing in memory anymore
        $this->assertEquals([], array_filter($this->memory->getMultiple(['A1', 'A2', 'A3', 'A4'])));

        // All 4 cells show be persisted
        $this->assertEquals([
            'A1' => 'A1-value',
            'A2' => 'A2-value',
            'A3' => 'A3-value',
            'A4' => 'A4-value',
        ], $this->cache->getMultiple(['A1', 'A2', 'A3', 'A4']));

        // Batch cache should return all 4 cells
        $this->assertEquals([
            'A1' => 'A1-value',
            'A2' => 'A2-value',
            'A3' => 'A3-value',
            'A4' => 'A4-value',
        ], $cache->getMultiple(['A1', 'A2', 'A3', 'A4']));
    }

    public function test_it_persists_to_cache_when_memory_limit_reached_on_setting_multiple_values()
    {
        $memoryLimit = 3;
        $persisted   = [];
        $inMemory    = [
            'A1' => 'A1-value',
            'A2' => 'A2-value',
            'A3' => 'A3-value',
        ];

        $cache = $this->givenCache($inMemory, $persisted, $memoryLimit);

        // Setting a 4th value will reach the memory limit
        $cache->setMultiple([
            'A4' => 'A4-value',
            'A5' => 'A5-value',
        ], 10000);

        // Nothing in memory anymore
        $this->assertEquals([], array_filter($this->memory->getMultiple(['A1', 'A2', 'A3', 'A4', 'A5'])));

        // All 4 cells show be persisted
        $this->assertEquals([
            'A1' => 'A1-value',
            'A2' => 'A2-value',
            'A3' => 'A3-value',
            'A4' => 'A4-value',
            'A5' => 'A5-value',
        ], $this->cache->getMultiple(['A1', 'A2', 'A3', 'A4', 'A5']));

        // Batch cache should return all 4 cells
        $this->assertEquals([
            'A1' => 'A1-value',
            'A2' => 'A2-value',
            'A3' => 'A3-value',
            'A4' => 'A4-value',
            'A5' => 'A5-value',
        ], $cache->getMultiple(['A1', 'A2', 'A3', 'A4', 'A5']));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('defaultTTLDataProvider')]
    public function test_it_writes_to_cache_with_default_ttl($defaultTTL, $receivedAs)
    {
        config()->set('excel.cache.default_ttl', $defaultTTL);

        $cache = $this->givenCache(['A1' => 'A1-value'], [], 1);
        $this->cache->setEventDispatcher(Event::fake());
        $cache->set('A2', 'A2-value');

        $expectedTTL = value($receivedAs);

        $dispatchedCollection = Event::dispatched(
            KeyWritten::class,
            function (KeyWritten $event) use ($expectedTTL) {
                return $event->seconds === $expectedTTL;
            });

        $this->assertCount(2, $dispatchedCollection);
    }

    public function test_it_writes_to_cache_with_a_dateinterval_ttl()
    {
        // DateInterval is 1 minute
        config()->set('excel.cache.default_ttl', new DateInterval('PT1M'));

        $cache = $this->givenCache(['A1' => 'A1-value'], [], 1);
        $this->cache->setEventDispatcher(Event::fake());
        $cache->set('A2', 'A2-value');

        $dispatchedCollection = Event::dispatched(
            KeyWritten::class,
            function (KeyWritten $event) {
                return $event->seconds >= 59 && $event->seconds <= 60;
            });

        $this->assertCount(2, $dispatchedCollection);
    }

    public function test_it_can_override_default_ttl()
    {
        config()->set('excel.cache.default_ttl', 1);

        $cache = $this->givenCache(['A1' => 'A1-value'], [], 1);
        $this->cache->setEventDispatcher(Event::fake());
        $cache->set('A2', 'A2-value', null);

        $dispatchedCollection = Event::dispatched(
            KeyWritten::class,
            function (KeyWritten $event) {
                return $event->seconds === null;
            });

        $this->assertCount(2, $dispatchedCollection);
    }

    public static function defaultTTLDataProvider(): array
    {
        return [
            'null (forever)' => [null, null],
            'int value'      => [$value = rand(1, 100), $value],
            'callable'       => [$closure = function () {
                return 199;
            }, $closure],
        ];
    }

    /**
     * Construct a BatchCache with a in memory store
     * and an array cache, pretending to be a persistence store.
     *
     * @param  array  $memory
     * @param  array  $persisted
     * @param  ?int  $memoryLimit
     * @return CacheInterface
     */
    private function givenCache(array $memory = [], array $persisted = [], ?int $memoryLimit = null): CacheInterface
    {
        config()->set('excel.cache.batch.memory_limit', $memoryLimit ?: 60000);

        $this->memory = $this->app->make(CacheManager::class)->createMemoryDriver();
        $this->memory->setMultiple($memory);

        $store = new ArrayStore();
        $store->putMany($persisted, 10000);

        $this->cache = new Repository($store);

        if (!InstalledVersions::satisfies(new VersionParser, 'psr/simple-cache', '^3.0')) {
            return new BatchCacheDeprecated(
                $this->cache,
                $this->memory,
                config('excel.cache.default_ttl')
            );
        }

        return new BatchCache(
            $this->cache,
            $this->memory,
            config('excel.cache.default_ttl')
        );
    }
}
