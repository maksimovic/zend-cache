<?php

use PHPUnit\Framework\TestCase;

class ZendCacheTestEntity
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function greet(string $name): string
    {
        echo "Hello, $name!";
        return $name;
    }

    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }
}

class ZendCacheTestStaticEntity
{
    public static function double(int $n): int
    {
        return $n * 2;
    }
}

class ZendCacheFrontendClassTest extends TestCase
{
    private function makeCache(object $entity, array $extraOptions = []): Zend_Cache_Frontend_Class
    {
        $options = array_merge([
            'cached_entity' => $entity,
            'write_control' => false,
        ], $extraOptions);
        $cache = new Zend_Cache_Frontend_Class($options);
        $cache->setBackend(new Zend_Cache_Backend_BlackHole());
        return $cache;
    }

    public function testCallCachesReturnValue(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity());
        $result = $cache->add(2, 3);
        $this->assertSame(5, $result);
    }

    public function testCallCapturesOutput(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity());
        ob_start();
        $result = $cache->greet('World');
        $output = ob_get_clean();

        $this->assertSame('World', $result);
        $this->assertSame('Hello, World!', $output);
    }

    public function testCallWithRealBackendServesFromCache(): void
    {
        $cacheDir = sys_get_temp_dir() . '/zend_cache_cls_' . uniqid();
        mkdir($cacheDir);

        try {
            $cache = new Zend_Cache_Frontend_Class([
                'cached_entity' => new ZendCacheTestEntity(),
                'write_control' => false,
            ]);
            $cache->setBackend(new Zend_Cache_Backend_File(['cache_dir' => $cacheDir]));

            $result1 = $cache->add(10, 20);
            $this->assertSame(30, $result1);

            // second call should come from cache
            $result2 = $cache->add(10, 20);
            $this->assertSame(30, $result2);
        } finally {
            $files = glob($cacheDir . '/*');
            if ($files) {
                array_map('unlink', $files);
            }
            @rmdir($cacheDir);
        }
    }

    public function testNonCachedMethods(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity(), [
            'non_cached_methods' => ['add'],
        ]);
        // Should still work, just not cached
        $result = $cache->add(5, 6);
        $this->assertSame(11, $result);
    }

    public function testCacheByDefaultFalseWithCachedMethods(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity(), [
            'cache_by_default' => false,
            'cached_methods' => ['add'],
        ]);
        $result = $cache->add(7, 8);
        $this->assertSame(15, $result);
    }

    public function testCacheByDefaultFalseNotListed(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity(), [
            'cache_by_default' => false,
        ]);
        // multiply is not in cached_methods, so bypasses cache
        $result = $cache->multiply(3, 4);
        $this->assertSame(12, $result);
    }

    public function testSetSpecificLifetime(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity());
        $cache->setSpecificLifetime(7200);
        $result = $cache->add(1, 1);
        $this->assertSame(2, $result);
    }

    public function testSetPriority(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity());
        $cache->setPriority(10);
        $result = $cache->add(1, 1);
        $this->assertSame(2, $result);
    }

    public function testSetTagsArray(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity());
        $cache->setTagsArray(['tag1', 'tag2']);
        $result = $cache->add(1, 1);
        $this->assertSame(2, $result);
    }

    public function testCallThrowsOnInvalidMethod(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity());
        $this->expectException(Zend_Cache_Exception::class);
        $cache->nonExistentMethod();
    }

    public function testCallWithStaticClass(): void
    {
        $cache = new Zend_Cache_Frontend_Class([
            'cached_entity' => 'ZendCacheTestStaticEntity',
            'write_control' => false,
        ]);
        $cache->setBackend(new Zend_Cache_Backend_BlackHole());
        $result = $cache->double(5);
        $this->assertSame(10, $result);
    }

    public function testConstructorThrowsWithoutEntity(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        new Zend_Cache_Frontend_Class([]);
    }

    public function testConstructorThrowsWithInvalidEntity(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        new Zend_Cache_Frontend_Class(['cached_entity' => 123]);
    }

    public function testMakeIdDeterministic(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity());
        $id1 = $cache->makeId('add', [1, 2]);
        $id2 = $cache->makeId('add', [1, 2]);
        $this->assertSame($id1, $id2);
    }

    public function testMakeIdDifferentForDifferentArgs(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity());
        $id1 = $cache->makeId('add', [1, 2]);
        $id2 = $cache->makeId('add', [3, 4]);
        $this->assertNotSame($id1, $id2);
    }

    public function testMakeIdDifferentForDifferentMethods(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity());
        $id1 = $cache->makeId('add', [1, 2]);
        $id2 = $cache->makeId('multiply', [1, 2]);
        $this->assertNotSame($id1, $id2);
    }

    public function testAutoSerializationEnabled(): void
    {
        $cache = $this->makeCache(new ZendCacheTestEntity());
        $this->assertTrue($cache->getOption('automatic_serialization'));
    }

    public function testExceptionInCachedMethodPropagates(): void
    {
        $entity = new class {
            public function fail(): void
            {
                throw new RuntimeException('boom');
            }
        };
        $cache = $this->makeCache($entity);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $cache->fail();
    }
}
