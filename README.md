# Warmable

[![Latest Version on Packagist](https://img.shields.io/packagist/v/henzeb/warmable.svg?style=flat-square)](https://packagist.org/packages/henzeb/warmable)
[![Total Downloads](https://img.shields.io/packagist/dt/henzeb/warmable.svg?style=flat-square)](https://packagist.org/packages/henzeb/warmable)

This package was inspired by a talk by
[@timacdonald](https://github.com/timacdonald) where he showed a technique to warm up a cache entry 
scheduled by using a simple invokable class. 

When you cache the result of a heavy operation, once in a while, some user
will have to wait for the operation to be cached again. If the request rate is
high enough, multiple people may have to do so at the same time.

This package provides the framework in which you can take all that away.
It is implementing the [PSR16](https://www.php-fig.org/psr/psr-16/) interface for caching, so it pretty much 
supports every caching mechanism you can think of, and if not, 
one can implement a wrapper, and it still supports every caching mechanism 
you can think of.

Laravel-developers can use this package as well, but in order to harvest
the full power of Laravel, it's better to install and use
[Warmable for Laravel](https://packagist.org/packages/henzeb/warmable-laravel)

## Installation

Just install with the following command.

```bash
composer require henzeb/warmable
```

## Terminology

#### Warm up / warming up

The term `warm up` or `warming up` refers to caching
during a cron job or in a queue job.

#### preheating

Preheating is the term used when the cache is populated during
execution, which is the default operation when the cache does not exist.
This may also be dispatched to a background operation like
a Queueing System.

## Usage

### Creating a Warmable

Creating a `Warmable` is pretty easy. 

```php
use Henzeb\Warmable\Warmable;
use Psr\SimpleCache\CacheInterface;
use DateTimeInterface;
use DateInterval;

class HeavyOperation extends Warmable
{
    protected function key() : string
    {
        // ... return your key
    }
    
    protected function ttl() : DateTimeInterface|DateInterval|int|null
    {
        // ... return your desired ttl
    }
    
    protected function gracePeriod() : DateTimeInterface|DateInterval|int|null
    {
        // ... return your desired grace period
    }
    
    protected function cache(): CacheInterface 
    {
         // ... your cache implementation
    }
    
    protected function warmable(): mixed 
    {
         // ... your heavy operation
    }
}
```

Note: The `key` and the `ttl` can be omitted. When omitting the `key`,
a key is generated based on the FQCN of your object. When `ttl` is omitted,
the `Warmable` result will be stored in cache forever.

## chaining methods.

`Warmable` allows chaining methods. You can start with any method you wish.

```php

HeavyOperation::withKey('yourKey')->get();

HeavyOperation::withTtl(300)->withKey('yourKey')->get();

HeavyOperation::withKey('yourKey')->withTtl(300)->get();
```

### make

With `make` you can easily get an instance of your `Warmable`. You can add
arguments that will be passed on to your constructor.

```php
// equivalent to new HeavyOperation();
HeavyOperation::make(); 

// equivalent to new HeavyOperation(new YourService());
HeavyOperation::make(new YourService()); 
```

### get

With this method you can easily access the cached data.

```php
HeavyOperation::get();
HeavyOperation::make()->get();
```

By default,`Warmable` preheats the data for the first time if it does
not exist yet. If you don't want that to happen before response, 
you can set a default. In that case it will postpone that action until
after the response is sent to the browser.

```php
HeavyOperation::get([]);

HeavyOperation::get(true);

HeavyOperation::get(fn()=>false);
```

Note: this doesn't work with `null`.
Use [withoutPreheating](#withoutPreheating) instead.

### with

The warmable method supports a rudimentary version of dependency injection.
Using this method, you can make your cache unique per user or item. 
You don´t have to do anything else besides passing it into the constructor.

```php
use Henzeb\Warmable\Warmable;

class HeavyOperation extends Warmable
{
    // ...
    
    public function warmable(int $id): mixed {
        // ...
        return 'this is '.$id;
    } 
}

HeavyOperation::with(12)->get(); // returns 'this is 12'
HeavyOperation::with(14)->get(); // returns 'this is 14'
HeavyOperation::with(12)->get(); // returns 'this is 12' from cache
HeavyOperation::with(14)->get(); // returns 'this is 14' from cache
```

### key

By default, `Warmable` will generate a unique key for you. You don't need to do
anything. Even if you want to reuse the `Warmable` for different items, 
`Warmable` got your back. But if you do want to customize the key, there are 2
ways to do it. 

The first is by setting the `key` method on your `Warmable` class.

```php
use Henzeb\Warmable\Warmable;

class HeavyOperation extends Warmable {
    protected function key() : string{
        return 'your-key';
    }    
}

HeavyOperation::get(); // retrieves from 'your-key'
HeavyOperation::with(12)->get(); // retrieves from 'your-key.<sha1 hash>'

``` 

The second option is inline: 

```php
HeavyOperation::withKey('other-key')->get(); // retrieves from 'other-key'
HeavyOperation::withKey('other-key')
->with(12)
->get(); // retrieves from 'other-key.<sha1 hash>'
``` 

### Cache 

The cache is, obviously, a required element. It's set through the mandatory
`cache` method. There is also a `withCache` method that can be used inline.

```php
use Psr\SimpleCache\CacheInterface;
HeavyOperation::withCache(<CacheInterface::class>);
HeavyOperation::make()->withCache(<CacheInterface::class>);
```

Note: `Warmable` accepts any Cache implementation that implements the PSR-16
`CacheInterface`. If the cache system you are using does not implement it, 
you can easily create a wrapper that does implement it.

### Time To Live (Ttl)

Allows you to use a different TTL than the one defined inside your class.

Note Ttl is `null`, and thus `forever`, by default.

```php
use Henzeb\Warmable\Warmable;

class HeavyOperation extends Warmable
{
    protected function ttl(): DateTimeInterface|DateInterval|int|null {
         return DateInterval::createFromDateString('300 seconds');
    }
}
```

Or you can set things inline inline:

```php
use Henzeb\Warmable\Warmable;

// expires on given date
HeavyOperation::withTtl(new DateTime('2024-02-31 23:00'));

// expires in 300 seconds
HeavyOperation::withTtl(
DateInterval::createFromDateString('300 seconds')
);

// expires in 300 seconds
HeavyOperation::withTtl(300);
```

### Grace periods

In combination with a Ttl, a grace period allows you to give the user the old data
while updating the cache. This updating happens after returning the response 
to the browser.

The example below will create a cache item that lives for 600 seconds. 300+
seconds in, the next call will return the value in the cache, and registers a 
shutdown function that updates the cache item, which will live for 600 seconds, 
and so on.

```php
use Henzeb\Warmable\Warmable;

class HeavyOperation extends Warmable
{
    protected function ttl(): DateTimeInterface|DateInterval|int|null {
         return 300
    }
    
    protected function gracePeriod(): DateTimeInterface|DateInterval|int|null {
         return 300
    }
}
```

### getKey

There may be occasions where you need to see the key used by your `Warmable`.

```php

// returns warmable.HeavyOperation
HeavyOperation::getKey();

// returns test
HeavyOperation::withKey('test')->getKey();
 
// returns test.<sha1 hash>
HeavyOperation::withKey('test')->with(id)->getKey(); 
```
### missing

```php
// returns true
HeavyOperation::missing();
// returns false
HeavyOperation::get(); // will preheat the cache
HeavyOperation::make()->missing();
HeavyOperation::missing();
```

### isPreheated

This method tells you if the cache was preheated.

```php
$operation = HeavyOperation::make();
$operation->isPreheated(); // returns false
$operation->get(); // will preheat the cache
$operation->isPreheated(); // returns true
```

### withoutPreheating

This method allows you to explicitly disable preheating.

```php
HeavyOperation::withoutPreheating()->get(); // returns null when not warmed up

// and when called again:
HeavyOperation::withoutPreheating()->get(); // returns null when not warmed up
```

### withPreheating

Works exactly like [withoutPreheating](#without),
except it switches the preheating on.

```php
// would preheat the warmable data.
HeavyOperation::withPreheating()->get(); 

// would preheat the data after shutdown, and return default instead.
HeavyOperation::withPreheating()->get([]); 
```

### shouldPreheat

A method that tells you if preheat flag is turned on

```php
HeavyOperation::shouldPreheat(); // returns true (default)
HeavyOperation::withPreheating()->shouldPreheat(); // returns true
HeavyOperation::withoutPreheating()->shouldPreheat(); // returns false
```

### cooldown

If for some reason you need to delete the cache, you can call `cooldown`.

```php
HeavyOperation::cooldown();
HeavyOperation::make()->cooldown();
```

### Custom warmup strategy

If your application has access to a queue, it is easy to change the 
preheat strategy. By default, it preheats during execution.

```php
use Henzeb\Warmable\Warmable;

class HeavyOperation extends Warmable
{
    // ... your Warmable definition
    
    protected function getPreheated(bool $hasDefault): mixed
    {
        // ...
    }
}
```

Note: Returning `null` causes the `get` method to use the default.

### Overriding public interface

Under the hood, `Warmable` uses `__call` and `__callStatic` to allow chaining
static and dynamic calls. If you want to override a method on the public interface,
you should find it's `call<method>` counterpart. 

For Example: If you want to override the `with` method, you should extend the 
`callWith` method.

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email henzeberkheij@gmail.com instead of using the issue tracker.

## Credits

- [Henze Berkheij](https://github.com/henzeb)

## License

The GNU AGPLv. Please see [License File](LICENSE.md) for more information.
