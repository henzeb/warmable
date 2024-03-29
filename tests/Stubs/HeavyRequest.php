<?php

namespace Henzeb\Warmable\Tests\Stubs;

use DateInterval;
use DateTimeInterface;
use Henzeb\Warmable\Support\HigherOrderWarmableProxy;
use Henzeb\Warmable\Warmable;
use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Psr\SimpleCache\CacheInterface;

class HeavyRequest extends Warmable
{
    public static CacheInterface|MockInterface|LegacyMockInterface|null $testCache = null;

    public static ?string $testkey = null;

    public static DateTimeInterface|DateInterval|int|null $testTtl = null;

    public static ?string $testHeatedUp = null;
    public static DateTimeInterface|DateInterval|int|null $testGrace = null;
    public static $testGetCache = null;
    public static $testResolves = null;
    /**
     * @var true
     */
    public static bool $testShutdown = false;

    protected function cache(): CacheInterface
    {
        return self::$testCache ?? Mockery::mock(CacheInterface::class)->makePartial();
    }

    protected function ttl(): DateTimeInterface|DateInterval|int|null
    {
        return static::$testTtl ?? parent::ttl();
    }

    protected function gracePeriod(): DateTimeInterface|DateInterval|int|null
    {
        return static::$testGrace ?? parent::gracePeriod();
    }

    protected function key(): string
    {
        return static::$testkey ?? parent::key();
    }

    protected function getPreheated(bool $hasDefault): mixed
    {
        return static::$testHeatedUp ?? parent::getPreheated($hasDefault);
    }

    protected function warmable(InjectedService $service = null): string
    {
        return 'Hello World';
    }

    protected function getCache(): CacheInterface
    {
        return self::$testGetCache ?? parent::getCache(); // TODO: Change the autogenerated stub
    }

    protected static function resolveNewInstance(): static
    {
        return self::$testResolves ?? parent::resolveNewInstance(); // TODO: Change the autogenerated stub
    }

    protected function afterShutdown(callable $afterShutdown): void
    {
        $afterShutdown();
    }

    public static function flushTest(): void
    {
        self::$testCache = null;
        self::$testTtl = null;
        self::$testGrace = null;
        self::$testkey = null;
        self::$testHeatedUp = null;
        self::$testGetCache = null;
        self::$testResolves = null;
        self::$testShutdown = false;
    }
}
