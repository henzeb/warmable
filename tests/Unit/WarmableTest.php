<?php

namespace Henzeb\Warmable\Tests\Unit;


use BadMethodCallException;
use DateInterval;
use Henzeb\DateTime\DateTime;
use Henzeb\Warmable\CacheItem;
use Henzeb\Warmable\Tests\Stubs\HeavyInjectedRequest;
use Henzeb\Warmable\Tests\Stubs\HeavyRequest;
use Henzeb\Warmable\Tests\Stubs\InjectedService;
use Henzeb\Warmable\Warmable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\SimpleCache\CacheInterface;
use ReflectionMethod;


class WarmableTest extends MockeryTestCase
{
    const CACHE_KEY = 'warmable.' . HeavyRequest::class;

    protected function setUp(): void
    {
        DateTime::setTestNow('2024-1-1');
    }

    public function testShouldGet()
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')->with(self::CACHE_KEY)
            ->andReturn('Hello World');

        $this->assertEquals('Hello World', HeavyRequest::get());
    }

    public function testShouldGetWithPreheat()
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')->with(self::CACHE_KEY)
            ->andReturn(null);

        HeavyRequest::$testCache->expects('set')->with(self::CACHE_KEY, 'Hello World', null)
            ->andReturn(true);

        HeavyRequest::$testCache->expects('get')->with(self::CACHE_KEY)
            ->andReturn('Hello World');

        $this->assertEquals('Hello World', HeavyRequest::get());
    }

    public function testShouldGetWithUserSpecifiedKey()
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')
            ->with('test')
            ->andReturn('Hello World');

        HeavyRequest::$testkey = 'test';

        $this->assertEquals('Hello World', HeavyRequest::get());
    }

    public static function providesTtlTestcases(): array
    {
        DateTime::setTestNow('2024-1-1');
        $interval = DateInterval::createFromDateString('300 seconds');
        return [
            'int' => [300, 300],
            'DateInterval' => [$interval, 300],
            'DateTimeInterface' => [(new DateTime)->add($interval), 300]
        ];
    }

    /**
     * @dataProvider providesTtlTestcases
     */
    public function testWithTtl(mixed $actual, $expected)
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')
            ->with(self::CACHE_KEY)
            ->andReturn(null);

        HeavyRequest::$testTtl = 290;

        HeavyRequest::$testCache->expects('set')
            ->with(self::CACHE_KEY, 'Hello World', $expected);

        HeavyRequest::withTtl($actual)->get();
    }

    /**
     * @dataProvider providesTtlTestcases
     */
    public function testMethodTtl(mixed $actual, $expected)
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')
            ->with(self::CACHE_KEY)
            ->andReturn(null);

        HeavyRequest::$testTtl = $actual;

        HeavyRequest::$testCache->expects('set')
            ->with(self::CACHE_KEY, 'Hello World', $expected);

        HeavyRequest::get();
    }

    /**
     * @dataProvider providesTtlTestcases
     */
    public function testWithGrace(mixed $actual, $expected)
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')
            ->with(self::CACHE_KEY)
            ->andReturn(null);

        HeavyRequest::$testGrace = 290;

        HeavyRequest::$testCache->expects('set')
            ->withArgs(
                function ($key, CacheItem $value, $ttl) use ($expected) {
                    return $key === self::CACHE_KEY
                        && $value->ttl === 1704067490
                        && $value->data === 'Hello World'
                        && $ttl === $expected + 290;
                }
            );

        HeavyRequest::withTtl(290)
            ->withGracePeriod($actual)
            ->get();
    }

    /**
     * @dataProvider providesTtlTestcases
     */
    public function testMethodGrace(mixed $actual, $expected)
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')
            ->with(self::CACHE_KEY)
            ->andReturn(null);

        HeavyRequest::$testTtl = 290;
        HeavyRequest::$testGrace = $actual;

        HeavyRequest::$testCache->expects('set')
            ->withArgs(
                function ($key, CacheItem $value, $ttl) use ($expected) {
                    return $key === self::CACHE_KEY
                        && $value->ttl === 1704067490
                        && $value->data === 'Hello World'
                        && $ttl === ($expected + 290);
                }
            );

        HeavyRequest::get();
    }

    public function testOverridinGetCache()
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')->never();

        HeavyRequest::$testGetCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testGetCache->expects('get')
            ->with(self::CACHE_KEY)
            ->andReturn('hello world');;

        HeavyRequest::get();
    }

    public function testShouldPreheatAndGetWithUserSpecifiedKey()
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')->with('test')
            ->andReturn(null);

        HeavyRequest::$testCache->expects('set')->with('test', 'Hello World', null)
            ->andReturn(true);

        HeavyRequest::$testCache->expects('get')->with('test')
            ->andReturn('Hello World');

        $this->assertEquals('Hello World', HeavyRequest::withKey('test')->get());
    }

    public function testShouldGetAfterMake()
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')
            ->andReturn('Hello World');

        $this->assertEquals('Hello World', HeavyRequest::make()->get());
    }

    public function testShouldMakeOnInstance()
    {
        $instance = HeavyRequest::make();

        $this->assertNotSame($instance, $instance->make());
    }

    public function testShouldGetWithDefault()
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')
            ->andReturn(null);

        HeavyRequest::$testCache->expects('set');

        $this->assertEquals('Hello Space', HeavyRequest::get('Hello Space'));
    }

    public function testShouldGetResultsWithDefaultSet()
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')
            ->andReturn('Hello World');

        $this->assertEquals('Hello World', HeavyRequest::get('Hello Space'));
    }

    public function testShouldGetWithCallableDefault()
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')
            ->andReturn(null);

        HeavyRequest::$testCache->expects('set');

        $this->assertEquals('Hello Space', HeavyRequest::get(fn() => 'Hello Space'));
    }

    public function testWithoutPreheatingShouldUseDifferentCache(): void
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')->never();

        $cache = Mockery::mock(CacheInterface::class);
        $cache->expects('get');

        HeavyRequest::withoutPreheating()
            ->withCache($cache)
            ->get();
    }

    public function testWithPreheatingShouldUseDifferentCache(): void
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('has')->never();
        HeavyRequest::$testCache->expects('get')->never();

        $cache = Mockery::mock(CacheInterface::class);
        $cache->expects('has')->andReturnTrue()->once();
        $cache->expects('get')->andReturn('Hello World')->once();

        HeavyRequest::withCache($cache)->withPreheating()->get();
    }

    public function testWithPreheatingShouldPreheatAlways(): void
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('has')->once()->andReturn(false);
        HeavyRequest::$testCache->expects('set')
            ->with(self::CACHE_KEY, 'Hello World', null);

        HeavyRequest::withPreheating();
    }

    public function testWithoutPreheating(): void
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('get')->andReturn(null);
        HeavyRequest::$testCache->expects('set')->never();
        $this->assertNull(HeavyRequest::withoutPreheating()->get());
    }


    public function testCooldownShouldDeleteCache(): void
    {
        HeavyRequest::$testCache = $cache = Mockery::mock(CacheInterface::class);

        $cache->expects('has')->never();
        $cache->expects('set')->never();
        $cache->expects('delete')->once();
        $cache->expects('delete')->with('test')->once();

        HeavyRequest::cooldown();
        HeavyRequest::withKey('test')->cooldown();
    }

    public function testDependencyInjection(): void
    {
        $service = new InjectedService();
        $service->value = 'Hello World';

        $cache = Mockery::mock(CacheInterface::class);

        $key = 'test.533cfc092e2fa0b66ca20f1dcaf80992fcd9627d';

        $cache->expects('get')->with($key)->once();

        $cache->expects('set')->with($key, 'Hello World', null)->once();

        HeavyInjectedRequest::withKey('test')
            ->withCache($cache)
            ->with(
                $service
            )->get();
    }

    public function testCustomDependencyInjection(): void
    {
        $service = new InjectedService();
        $service->value = 'Hello World';
        HeavyInjectedRequest::$callWarmable = 'Intervened Hello';

        $cache = Mockery::mock(CacheInterface::class);

        $key = 'test.533cfc092e2fa0b66ca20f1dcaf80992fcd9627d';

        $cache->expects('get')->with($key)->once();

        $cache->expects('set')->with($key, 'Intervened Hello', null)->once();

        HeavyInjectedRequest::withKey('test')
            ->withCache($cache)
            ->with(
                $service
            )->get();
    }


    public function testDependencyInjectionMake(): void
    {
        $warmable = new class('hello space') extends Warmable {

            public function __construct(public string $actual)
            {
            }

            protected function cache(): CacheInterface
            {
            }
        };

        $this->assertEquals('hello world', $warmable::make('hello world')->actual);
    }

    public function testShouldOverrideParametersOfWrappedWarmableInstance()
    {
        $oldCache = Mockery::mock(CacheInterface::class);
        $cache = Mockery::mock(CacheInterface::class);

        $cache->expects('get')
            ->with(
                'different_key',
            )->once();
        $cache->expects('set')
            ->withArgs(
                function ($key, CacheItem $value, $ttl){
                    return $key === 'different_key'
                        && $value->ttl === 1704067500
                        && $value->data === 'Hello World'
                        && $ttl === 420;
                }
            )->once();

        HeavyRequest::withKey(
            'first_key',
        )->withTtl(
            1200
        )->withGracePeriod(
            300
        )->withCache(
            $oldCache
        )->withKey(
            'different_key',
        )->withTtl(
            300
        )->withGracePeriod(
            120
        )->withCache(
            $cache
        )->get();
    }

    public function testShouldAllowNullInConfigurationOfInstance()
    {
        $cache = Mockery::mock(CacheInterface::class);

        $cache->expects('get')
            ->with(
                'first_key',
            )->once();
        $cache->expects('set')
            ->with(
                'first_key',
                'Hello World',
                1200,

            )->once();

        HeavyRequest::withKey(
            'first_key',
        )->withTtl(
            1200
        )->withCache(
            $cache
        )->withKey(
            null,
        )->withTtl(
            null
        )->withCache(
            null
        )->get();
    }

    public function testGracePeriodShouldBeRespected(): void
    {
        $ttl = (new DateTime)->getTimestamp() + 10;
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testGrace = 5;
        HeavyRequest::$testShutdown = true;

        HeavyRequest::$testCache->expects('get')->andReturn(
            new CacheItem($ttl, 'Hello World')
        )->times(2);


        $this->assertEquals('Hello World', HeavyRequest::get());

        HeavyRequest::$testCache->expects('set')->andReturnTrue();
        HeavyRequest::$testCache->expects('get')->andReturn(
            ['ttl' => $ttl, 'result' => 'Hello World2']
        )->once();
        DateTime::setTestNow('2024-01-01 00:00:10');

        $this->assertEquals('Hello World', HeavyRequest::get());

        HeavyRequest::$testCache->expects('set')->andReturnTrue();
        HeavyRequest::$testCache->expects('get')->andReturn(
            new CacheItem($ttl, 'Hello World')
        )->times(2);


        $this->assertEquals('Hello World', HeavyRequest::get());
    }

    public function testWarmupShouldNotHappenTwice(): void
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('set')->once()->andReturnTrue();
        $warmable = HeavyRequest::make();

        $this->assertTrue($warmable->warmup());
        $this->assertTrue($warmable->warmup());
    }

    public function testIsPreheated(): void
    {
        HeavyRequest::$testCache = Mockery::mock(CacheInterface::class);
        HeavyRequest::$testCache->expects('set')->once()->andReturnTrue();
        $warmable = HeavyRequest::make();

        $this->assertFalse($warmable->isPreheated());

        $warmable->warmup();

        $this->assertTrue($warmable->isPreheated());
    }

    public function testShouldPreheatMethod(): void
    {
        $warmable = HeavyRequest::make();

        $this->assertTrue($warmable->shouldPreheat());

        $warmable = $warmable->withoutPreheating();

        $this->assertFalse($warmable->shouldPreheat());
    }

    public function testShouldAllowOverridingNewinstanceResolver(): void
    {
        $notExpected = HeavyRequest::make();

        $expected = new class extends HeavyRequest {
        };

        HeavyRequest::$testResolves = $expected;

        $actual = HeavyRequest::make();

        $this->assertSame($expected, $actual);

        $this->assertNotSame($notExpected, $actual);
    }

    public function testgetPreheatedShouldBeProtected(): void
    {
        $reflection = new ReflectionMethod(
            new class extends Warmable {
                protected function cache(): CacheInterface
                {
                }
            },
            'getPreheated'
        );
        $this->assertTrue($reflection->isProtected());
    }

    public function testShouldThrowBadMethodCall(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage(
            'Call to undefined method Henzeb\Warmable\Tests\Stubs\HeavyRequest::doesNotExist()'
        );
        HeavyRequest::doesNotExist();
    }


    public static function providesProtectedMethods(): array
    {
        return [
            'callMake' => ['callMake'],
            'resolveNewInstance' => ['resolveNewInstance'],
            'callWith' => ['callWith'],
            'callGetKey' => ['callGetKey'],
            'callWithTtl' => ['callWithTtl'],
            'callWithGracePeriod' => ['callWithGracePeriod'],
            'callWithKey' => ['callWithKey'],
            'callMissing' => ['callMissing'],
            'callShouldPreheat' => ['callShouldPreheat'],
            'callGet' => ['callGet'],
            'calculateHash' => ['calculateHash'],
            'callWithCache' => ['callWithCache'],
            'callWithPreheating' => ['callWithPreheating'],
            'callWithoutPreheating' => ['callWithoutPreheating'],
            'getTtl' => ['getTtl'],
            'getGracePeriod' => ['getGracePeriod'],
            'callCooldown' => ['callCooldown'],
            'afterShutdown' => ['afterShutdown']

        ];
    }


    /**
     * @dataProvider providesProtectedMethods
     */
    public function testShouldBeProtected(string $method): void
    {
        $reflection = new ReflectionMethod(Warmable::class, $method);

        $this->assertTrue($reflection->isProtected());
    }

    protected function tearDown(): void
    {
        HeavyRequest::flushTest();
    }
}
