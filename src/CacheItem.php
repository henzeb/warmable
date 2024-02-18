<?php

namespace Henzeb\Warmable;

class CacheItem
{
    public function __construct(
        public int $ttl,
        public mixed $data
    )
    {
    }
}
