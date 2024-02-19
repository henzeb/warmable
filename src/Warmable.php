<?php

namespace Henzeb\Warmable;

use BadMethodCallException;
use DateInterval;
use DateTimeInterface;
use Henzeb\DateTime\DateTime;
use Psr\SimpleCache\CacheInterface;

/**
 * @method static $this make(mixed ...$parameters)
 *
 * @method static mixed get(mixed $default = null)
 * @method mixed get(mixed $default = null)
 *
 * @method static $this with(mixed ...$arguments)
 * @method $this with(mixed ...$arguments)
 *
 * @method static $this withKey(?string $key)
 * @method $this withKey(?string $key)
 *
 * @method static $this withCache(?CacheInterface $key)
 * @method $this withCache(?CacheInterface $key)
 *
 * @method static $this withTtl(?DateTimeInterface|DateInterval|int $ttl)
 * @method $this withTtl(?DateTimeInterface|DateInterval|int $ttl)
 *
 * @method static $this withGracePeriod(?DateTimeInterface|DateInterval|int $gracePeriod)
 * @method $this withGracePeriod(?DateTimeInterface|DateInterval|int $gracePeriod)
 *
 * @method static string getKey()
 * @method string getKey()
 *
 * @method static bool missing()
 * @method bool missing()
 *
 * @method static bool shouldPreheat()
 * @method bool shouldPreheat()
 *
 * @method static $this withPreheating()
 * @method $this withPreheating()
 *
 * @method static $this withoutPreheating()
 * @method $this withoutPreheating()
 *
 * @method static bool cooldown()
 * @method bool cooldown()
 *
 */
abstract class Warmable
{
    protected ?string $key = null;
    protected DateTimeInterface|DateInterval|int|null $ttl = null;
    protected DateTimeInterface|DateInterval|int|null $grace = null;
    protected ?CacheInterface $cache = null;
    protected bool $preheat = true;

    protected bool $preheated = false;

    protected array $with = [];

    abstract protected function cache(): CacheInterface;

    protected function callMake(mixed ...$arguments): static
    {
        return static::resolveNewInstance(...$arguments);
    }

    protected function key(): string
    {
        return 'warmable.' . static::class;
    }

    protected function ttl(): DateTimeInterface|DateInterval|int|null
    {
        return null;
    }

    protected function gracePeriod(): DateTimeInterface|DateInterval|int|null
    {
        return null;
    }

    protected function callWith(mixed ...$values): static
    {
        $this->with = func_get_args();

        return $this;
    }

    public function warmup(): bool
    {
        if ($this->preheated) {
            return true;
        }

        $warmedUp = $this->executeWarmable();

        $ttl = $this->getTtl();
        $grace = $this->getGracePeriod();

        if ($ttl && $grace) {
            $warmedUp = $this->wrapInCacheItem(
                (new DateTime)->getTimestamp() + $ttl,
                $warmedUp
            );

            $ttl = $ttl + $grace;
        }

        $this->preheated = $this->getCache()
            ->set(
                $this->getKey(),
                $warmedUp,
                $ttl
            );

        return $this->preheated;
    }

    protected function wrapInCacheItem(int $ttl, mixed $data): CacheItem
    {
        return new CacheItem($ttl, $data);
    }

    protected function executeWarmable(): mixed
    {
        return $this->warmable(...$this->with);
    }

    protected function getCache(): CacheInterface
    {
        return $this->cache ?? $this->cache();
    }

    protected function callGetKey(): string
    {
        $unique = !empty($this->with)
            ? '.' . $this->calculateHash($this->with)
            : null;

        return ($this->key ?? $this->key()) . $unique;
    }

    protected function calculateHash(array $with): string
    {
        return sha1(serialize($with));
    }

    protected function getTtl(): ?int
    {
        return $this->getSeconds(
            $this->ttl ?? $this->ttl()
        );
    }

    protected function getGracePeriod(): ?int
    {
        return $this->getSeconds(
            $this->grace ?? $this->gracePeriod()
        );
    }

    private function getSeconds(
        DateTimeInterface|DateInterval|int|null $period
    ): ?int
    {
        if ($period === null) {
            return null;
        }

        if (is_int($period)) {
            return $period;
        }

        $currentDateTime = (new DateTime());

        if ($period instanceof DateInterval) {
            $period = (clone $currentDateTime)->add($period);
        }

        return $period->getTimestamp() - $currentDateTime->getTimestamp();
    }

    protected function callMissing(): bool
    {
        return $this->getCache()
                ->has($this->getKey()) === false;
    }

    protected function callShouldPreheat(): bool
    {
        return $this->preheat;
    }

    public function isPreheated(): bool
    {
        return $this->preheated;
    }

    protected function callGet(
        mixed $default = null
    ): mixed
    {
        $result = $this->getCache()
            ->get(
                $this->getKey()
            );

        if ($this->shouldPreheat()) {
            if ($result instanceof CacheItem && (new DateTime())->getTimestamp() >= $result->ttl) {
                $this->afterShutdown(
                    fn() => $this->getPreheated((bool)$default)
                );
            } else if (null === $result) {
                $result = $this->getPreheated(!!$default);
            }
        }

        if ($result instanceof CacheItem) {
            $result = $result->data;
        }

        return $result ?? $this->getDefaultValue($default);
    }

    protected function getPreheated(bool $hasDefault): mixed
    {
        if (!$hasDefault && $this->warmup()) {
            return $this->getCache()->get($this->getKey());
        }

        if ($hasDefault) {
            $this->afterShutdown(fn() => $this->warmup());
        }

        return null;
    }

    protected function afterShutdown(callable $afterShutdown): void
    {
        register_shutdown_function($afterShutdown);
    }

    protected function callWithPreheating(): static
    {
        $this->preheat = true;
        return $this;
    }

    protected function callWithoutPreheating(): static
    {
        $this->preheat = false;

        return $this;
    }

    protected function callWithKey(?string $key): static
    {
        $this->key = $key ?? $this->key;

        return $this;
    }


    protected function callWithTtl(DateTimeInterface|DateInterval|int|null $ttl): static
    {
        $this->ttl = $ttl ?? $this->ttl;

        return $this;
    }

    protected function callWithGracePeriod(
        DateTimeInterface|DateInterval|int|null $gracePeriod
    ): static
    {
        $this->grace = $gracePeriod ?? $this->grace;

        return $this;
    }

    protected function callWithCache(?CacheInterface $cache): static
    {
        $this->cache = $cache ?? $this->cache;

        return $this;
    }

    protected function callCooldown(): bool
    {
        return $this->getCache()
            ->delete(
                key: $this->getKey()
            );
    }

    /**
     * Allows to parse a default from a closure
     *
     * @param mixed $defaultValue
     * @return mixed
     */
    private function getDefaultValue(mixed $defaultValue): mixed
    {
        if (is_callable($defaultValue)) {
            return $defaultValue();
        }

        return $defaultValue;
    }

    protected static function resolveNewInstance(): static
    {
        return new static(...func_get_args());
    }

    public function __call(string $name, array $arguments): mixed
    {
        $method = 'call' . $name;

        if (method_exists($this, $method)) {
            return $this->{$method}(...$arguments);
        }

        throw new BadMethodCallException(
            sprintf(
                "Call to undefined method %s::%s()",
                static::class,
                $name
            )
        );
    }

    public static function __callStatic(string $name, array $arguments): mixed
    {
        if ($name === 'make') {
            return static::resolveNewInstance(...$arguments);
        }

        return static::resolveNewInstance()->{$name}(...$arguments);
    }
}
