<?php

use PHPUnit\Framework\TestCase;

class ZendCacheBackendFileTest extends TestCase
{
    private Zend_Cache_Backend_File $backend;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/zend_cache_file_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->backend = new Zend_Cache_Backend_File(['cache_dir' => $this->cacheDir]);
    }

    protected function tearDown(): void
    {
        $this->backend->clean(Zend_Cache::CLEANING_MODE_ALL);
        $files = glob($this->cacheDir . '/*');
        if ($files) {
            array_map('unlink', $files);
        }
        @rmdir($this->cacheDir);
    }

    public function testSaveAndLoad(): void
    {
        $this->assertTrue($this->backend->save('hello world', 'test_id'));
        $this->assertSame('hello world', $this->backend->load('test_id'));
    }

    public function testLoadMiss(): void
    {
        $this->assertFalse($this->backend->load('nonexistent'));
    }

    public function testSaveOverwrite(): void
    {
        $this->backend->save('first', 'key1');
        $this->backend->save('second', 'key1');
        $this->assertSame('second', $this->backend->load('key1'));
    }

    public function testRemove(): void
    {
        $this->backend->save('data', 'key1');
        $this->assertTrue($this->backend->remove('key1'));
        $this->assertFalse($this->backend->load('key1'));
    }

    public function testRemoveNonExistent(): void
    {
        $this->assertFalse($this->backend->remove('nonexistent'));
    }

    public function testTest(): void
    {
        $this->backend->save('data', 'key1');
        $result = $this->backend->test('key1');
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testTestMiss(): void
    {
        $this->assertFalse($this->backend->test('nonexistent'));
    }

    public function testExpiredEntry(): void
    {
        $this->backend->save('data', 'key1', [], 3600);
        $this->backend->___expire('key1');
        $this->assertFalse($this->backend->load('key1'));
        $this->assertFalse($this->backend->test('key1'));
    }

    public function testExpiredEntryWithDoNotTestValidity(): void
    {
        $this->backend->save('data', 'key1', [], 3600);
        $this->backend->___expire('key1');
        $this->assertSame('data', $this->backend->load('key1', true));
    }

    public function testCleanAll(): void
    {
        $this->backend->save('data1', 'key1');
        $this->backend->save('data2', 'key2');
        $this->assertTrue($this->backend->clean(Zend_Cache::CLEANING_MODE_ALL));
        $this->assertFalse($this->backend->load('key1'));
        $this->assertFalse($this->backend->load('key2'));
    }

    public function testCleanOld(): void
    {
        $this->backend->save('data1', 'key1', [], 3600);
        $this->backend->save('data2', 'key2', [], 3600);
        $this->backend->___expire('key1');
        $this->assertTrue($this->backend->clean(Zend_Cache::CLEANING_MODE_OLD));
        $this->assertFalse($this->backend->load('key1'));
        $this->assertSame('data2', $this->backend->load('key2'));
    }

    public function testTags(): void
    {
        $this->backend->save('data1', 'key1', ['tag1', 'tag2']);
        $this->backend->save('data2', 'key2', ['tag2', 'tag3']);
        $this->backend->save('data3', 'key3', ['tag3']);

        $tags = $this->backend->getTags();
        $this->assertEqualsCanonicalizing(['tag1', 'tag2', 'tag3'], $tags);
    }

    public function testGetIds(): void
    {
        $this->backend->save('data1', 'key1');
        $this->backend->save('data2', 'key2');
        $ids = $this->backend->getIds();
        $this->assertEqualsCanonicalizing(['key1', 'key2'], $ids);
    }

    public function testGetIdsMatchingTags(): void
    {
        $this->backend->save('data1', 'key1', ['tag1', 'tag2']);
        $this->backend->save('data2', 'key2', ['tag2', 'tag3']);

        $ids = $this->backend->getIdsMatchingTags(['tag1', 'tag2']);
        $this->assertSame(['key1'], $ids);

        $ids = $this->backend->getIdsMatchingTags(['tag2']);
        $this->assertEqualsCanonicalizing(['key1', 'key2'], $ids);
    }

    public function testGetIdsNotMatchingTags(): void
    {
        $this->backend->save('data1', 'key1', ['tag1']);
        $this->backend->save('data2', 'key2', ['tag2']);
        $this->backend->save('data3', 'key3', []);

        $ids = $this->backend->getIdsNotMatchingTags(['tag1']);
        $this->assertEqualsCanonicalizing(['key2', 'key3'], $ids);
    }

    public function testGetIdsMatchingAnyTags(): void
    {
        $this->backend->save('data1', 'key1', ['tag1']);
        $this->backend->save('data2', 'key2', ['tag2']);
        $this->backend->save('data3', 'key3', ['tag3']);

        $ids = $this->backend->getIdsMatchingAnyTags(['tag1', 'tag3']);
        $this->assertEqualsCanonicalizing(['key1', 'key3'], $ids);
    }

    public function testCleanMatchingTag(): void
    {
        $this->backend->save('data1', 'key1', ['tag1']);
        $this->backend->save('data2', 'key2', ['tag2']);
        $this->backend->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, ['tag1']);
        $this->assertFalse($this->backend->load('key1'));
        $this->assertSame('data2', $this->backend->load('key2'));
    }

    public function testCleanNotMatchingTag(): void
    {
        $this->backend->save('data1', 'key1', ['tag1']);
        $this->backend->save('data2', 'key2', ['tag2']);
        $this->backend->save('data3', 'key3', []);
        $this->backend->clean(Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG, ['tag1']);
        $this->assertSame('data1', $this->backend->load('key1'));
        $this->assertFalse($this->backend->load('key2'));
        $this->assertFalse($this->backend->load('key3'));
    }

    public function testCleanMatchingAnyTag(): void
    {
        $this->backend->save('data1', 'key1', ['tag1']);
        $this->backend->save('data2', 'key2', ['tag2']);
        $this->backend->save('data3', 'key3', ['tag3']);
        $this->backend->clean(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, ['tag1', 'tag3']);
        $this->assertFalse($this->backend->load('key1'));
        $this->assertSame('data2', $this->backend->load('key2'));
        $this->assertFalse($this->backend->load('key3'));
    }

    public function testGetMetadatas(): void
    {
        $this->backend->save('data', 'key1', ['tag1', 'tag2']);
        $meta = $this->backend->getMetadatas('key1');
        $this->assertIsArray($meta);
        $this->assertEqualsCanonicalizing(['tag1', 'tag2'], $meta['tags']);
        $this->assertIsInt($meta['mtime']);
        $this->assertIsInt($meta['expire']);
    }

    public function testGetMetadatasNonExistent(): void
    {
        $this->assertFalse($this->backend->getMetadatas('nonexistent'));
    }

    public function testGetMetadatasExpired(): void
    {
        $this->backend->save('data', 'key1', [], 3600);
        $this->backend->___expire('key1');
        $this->assertFalse($this->backend->getMetadatas('key1'));
    }

    public function testTouch(): void
    {
        $this->backend->save('data', 'key1', [], 100);
        $meta1 = $this->backend->getMetadatas('key1');
        $this->assertTrue($this->backend->touch('key1', 200));
        $meta2 = $this->backend->getMetadatas('key1');
        $this->assertGreaterThan($meta1['expire'], $meta2['expire']);
    }

    public function testTouchNonExistent(): void
    {
        $this->assertFalse($this->backend->touch('nonexistent', 100));
    }

    public function testTouchExpired(): void
    {
        $this->backend->save('data', 'key1', [], 3600);
        $this->backend->___expire('key1');
        $this->assertFalse($this->backend->touch('key1', 200));
    }

    public function testGetCapabilities(): void
    {
        $caps = $this->backend->getCapabilities();
        $this->assertTrue($caps['automatic_cleaning']);
        $this->assertTrue($caps['tags']);
        $this->assertTrue($caps['expired_read']);
        $this->assertFalse($caps['priority']);
        $this->assertTrue($caps['infinite_lifetime']);
        $this->assertTrue($caps['get_list']);
    }

    public function testGetFillingPercentage(): void
    {
        $pct = $this->backend->getFillingPercentage();
        $this->assertIsInt($pct);
        $this->assertGreaterThanOrEqual(0, $pct);
        $this->assertLessThanOrEqual(100, $pct);
    }

    public function testInfiniteLifetime(): void
    {
        $this->backend->save('data', 'key1', [], null);
        $this->assertSame('data', $this->backend->load('key1'));
        $meta = $this->backend->getMetadatas('key1');
        $this->assertGreaterThan(time() + 1000000, $meta['expire']);
    }

    public function testReadControlTypes(): void
    {
        foreach (['md5', 'crc32', 'adler32', 'strlen'] as $type) {
            $cacheDir = sys_get_temp_dir() . '/zend_cache_rc_' . $type . '_' . uniqid();
            mkdir($cacheDir);
            $backend = new Zend_Cache_Backend_File([
                'cache_dir' => $cacheDir,
                'read_control_type' => $type,
            ]);
            $backend->save('test data', 'rctest');
            $this->assertSame('test data', $backend->load('rctest'));
            $backend->clean(Zend_Cache::CLEANING_MODE_ALL);
            $files = glob($cacheDir . '/*');
            if ($files) {
                array_map('unlink', $files);
            }
            @rmdir($cacheDir);
        }
    }

    public function testNoReadControl(): void
    {
        $cacheDir = sys_get_temp_dir() . '/zend_cache_norc_' . uniqid();
        mkdir($cacheDir);
        $backend = new Zend_Cache_Backend_File([
            'cache_dir' => $cacheDir,
            'read_control' => false,
        ]);
        $backend->save('test data', 'norctest');
        $this->assertSame('test data', $backend->load('norctest'));
        $backend->clean(Zend_Cache::CLEANING_MODE_ALL);
        $files = glob($cacheDir . '/*');
        if ($files) {
            array_map('unlink', $files);
        }
        @rmdir($cacheDir);
    }

    public function testConstructorThrowsOnInvalidCacheDir(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        new Zend_Cache_Backend_File(['cache_dir' => '/nonexistent/path']);
    }

    public function testConstructorThrowsOnInvalidFileNamePrefix(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        new Zend_Cache_Backend_File([
            'cache_dir' => $this->cacheDir,
            'file_name_prefix' => 'invalid prefix!',
        ]);
    }

    public function testConstructorThrowsOnSmallMetadatasArrayMaxSize(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        new Zend_Cache_Backend_File([
            'cache_dir' => $this->cacheDir,
            'metadatas_array_max_size' => 5,
        ]);
    }

    public function testLargeData(): void
    {
        $data = str_repeat('x', 100000);
        $this->assertTrue($this->backend->save($data, 'large_key'));
        $this->assertSame($data, $this->backend->load('large_key'));
    }
}
