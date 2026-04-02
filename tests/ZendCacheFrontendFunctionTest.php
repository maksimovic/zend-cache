<?php

use PHPUnit\Framework\TestCase;

function zend_cache_test_function_add(int $a, int $b): int
{
    return $a + $b;
}

function zend_cache_test_function_echo(string $msg): string
{
    echo $msg;
    return 'returned';
}

class ZendCacheTestCallbackHelper
{
    public function getValue(): int { return 42; }
}

class ZendCacheFrontendFunctionTest extends TestCase
{
    private Zend_Cache_Frontend_Function $cache;

    protected function setUp(): void
    {
        $this->cache = new Zend_Cache_Frontend_Function([
            'caching' => true,
            'write_control' => false,
        ]);
        $this->cache->setBackend(new Zend_Cache_Backend_BlackHole());
    }

    public function testCallCachesReturnValue(): void
    {
        $result = $this->cache->call('zend_cache_test_function_add', [2, 3]);
        $this->assertSame(5, $result);
    }

    public function testCallCapturesOutput(): void
    {
        ob_start();
        $result = $this->cache->call('zend_cache_test_function_echo', ['hello']);
        $output = ob_get_clean();

        $this->assertSame('returned', $result);
        $this->assertSame('hello', $output);
    }

    public function testCallWithCachingDisabled(): void
    {
        $this->cache->setOption('caching', false);
        $result = $this->cache->call('zend_cache_test_function_add', [3, 4]);
        $this->assertSame(7, $result);
    }

    public function testCallWithNonCachedFunction(): void
    {
        $cache = new Zend_Cache_Frontend_Function([
            'non_cached_functions' => ['zend_cache_test_function_add'],
            'write_control' => false,
        ]);
        $cache->setBackend(new Zend_Cache_Backend_BlackHole());
        $result = $cache->call('zend_cache_test_function_add', [1, 1]);
        $this->assertSame(2, $result);
    }

    public function testCallWithCacheByDefaultFalse(): void
    {
        $cache = new Zend_Cache_Frontend_Function([
            'cache_by_default' => false,
            'cached_functions' => ['zend_cache_test_function_add'],
            'write_control' => false,
        ]);
        $cache->setBackend(new Zend_Cache_Backend_BlackHole());
        // This function IS in cached_functions, so it should be cached
        $result = $cache->call('zend_cache_test_function_add', [5, 6]);
        $this->assertSame(11, $result);
    }

    public function testCallWithCacheByDefaultFalseAndNotListed(): void
    {
        $cache = new Zend_Cache_Frontend_Function([
            'cache_by_default' => false,
            'write_control' => false,
        ]);
        $cache->setBackend(new Zend_Cache_Backend_BlackHole());
        // Not in cached_functions, so should bypass cache
        $result = $cache->call('zend_cache_test_function_add', [7, 8]);
        $this->assertSame(15, $result);
    }

    public function testCallWithRealBackendServesFromCache(): void
    {
        $cacheDir = sys_get_temp_dir() . '/zend_cache_fn_' . uniqid();
        mkdir($cacheDir);

        try {
            $cache = new Zend_Cache_Frontend_Function([
                'write_control' => false,
            ]);
            $cache->setBackend(new Zend_Cache_Backend_File(['cache_dir' => $cacheDir]));

            ob_start();
            $result1 = $cache->call('zend_cache_test_function_echo', ['first']);
            ob_end_clean();

            // second call should come from cache
            ob_start();
            $result2 = $cache->call('zend_cache_test_function_echo', ['first']);
            $output = ob_get_clean();

            $this->assertSame('returned', $result2);
            $this->assertSame('first', $output);
        } finally {
            $files = glob($cacheDir . '/*');
            if ($files) {
                array_map('unlink', $files);
            }
            @rmdir($cacheDir);
        }
    }

    public function testCallThrowsOnInvalidCallback(): void
    {
        $level = ob_get_level();
        try {
            $this->cache->call('this_function_does_not_exist_xyz');
            $this->fail('Expected TypeError');
        } catch (\TypeError $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            $this->assertStringContainsString('this_function_does_not_exist_xyz', $e->getMessage());
        }
    }

    public function testMakeIdDeterministic(): void
    {
        $id1 = $this->cache->makeId('zend_cache_test_function_add', [1, 2]);
        $id2 = $this->cache->makeId('zend_cache_test_function_add', [1, 2]);
        $this->assertSame($id1, $id2);
    }

    public function testMakeIdDifferentForDifferentArgs(): void
    {
        $id1 = $this->cache->makeId('zend_cache_test_function_add', [1, 2]);
        $id2 = $this->cache->makeId('zend_cache_test_function_add', [3, 4]);
        $this->assertNotSame($id1, $id2);
    }

    public function testMakeIdDifferentForDifferentFunctions(): void
    {
        $id1 = $this->cache->makeId('zend_cache_test_function_add', [1, 2]);
        $id2 = $this->cache->makeId('zend_cache_test_function_echo', [1, 2]);
        $this->assertNotSame($id1, $id2);
    }

    public function testMakeIdWithArrayCallback(): void
    {
        $obj = new ZendCacheTestCallbackHelper();
        $id = $this->cache->makeId([$obj, 'getValue']);
        $this->assertIsString($id);
        $this->assertSame(32, strlen($id));
    }

    public function testAutoSerializationEnabled(): void
    {
        $this->assertTrue($this->cache->getOption('automatic_serialization'));
    }
}
