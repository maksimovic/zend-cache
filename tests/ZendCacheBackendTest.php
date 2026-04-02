<?php

use PHPUnit\Framework\TestCase;

class ZendCacheBackendTest extends TestCase
{
    public function testBlackHoleBackendSaveAndLoad(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertTrue($backend->save('data', 'id1'));
        $this->assertFalse($backend->load('id1'));
    }

    public function testBlackHoleBackendRemove(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertTrue($backend->remove('id1'));
    }

    public function testBlackHoleBackendClean(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertTrue($backend->clean());
    }

    public function testBlackHoleBackendTest(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertFalse($backend->test('id1'));
    }

    public function testBlackHoleBackendGetIds(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertSame([], $backend->getIds());
    }

    public function testBlackHoleBackendGetTags(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertSame([], $backend->getTags());
    }

    public function testBlackHoleBackendGetCapabilities(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $caps = $backend->getCapabilities();
        $this->assertTrue($caps['automatic_cleaning']);
        $this->assertTrue($caps['tags']);
        $this->assertTrue($caps['priority']);
    }

    public function testBlackHoleBackendGetFillingPercentage(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertSame(0, $backend->getFillingPercentage());
    }

    public function testBlackHoleBackendTouch(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertFalse($backend->touch('id1', 100));
    }

    public function testBlackHoleBackendGetMetadatas(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertFalse($backend->getMetadatas('id1'));
    }

    public function testBaseBackendSetDirectives(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $backend->setDirectives(['lifetime' => 7200]);
        $this->assertSame(7200, $backend->getOption('lifetime'));
    }

    public function testBaseBackendGetLifetime(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertSame(3600, $backend->getLifetime(false));
        $this->assertSame(100, $backend->getLifetime(100));
    }

    public function testBaseBackendIsAutomaticCleaningAvailable(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertTrue($backend->isAutomaticCleaningAvailable());
    }

    public function testBaseBackendGetTmpDir(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $dir = $backend->getTmpDir();
        $this->assertDirectoryExists($dir);
    }

    public function testBaseBackendGetOptionThrowsOnInvalid(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->expectException(Zend_Cache_Exception::class);
        $backend->getOption('nonexistent');
    }

    public function testFileBackendSaveAndLoad(): void
    {
        $cacheDir = sys_get_temp_dir() . '/zend_cache_test_' . uniqid();
        mkdir($cacheDir);

        try {
            $backend = new Zend_Cache_Backend_File(['cache_dir' => $cacheDir]);
            $this->assertTrue($backend->save('hello', 'test_id'));
            $this->assertSame('hello', $backend->load('test_id'));
            $this->assertTrue($backend->remove('test_id'));
            $this->assertFalse($backend->load('test_id'));
        } finally {
            array_map('unlink', glob($cacheDir . '/*'));
            rmdir($cacheDir);
        }
    }

    public function testFileBackendClean(): void
    {
        $cacheDir = sys_get_temp_dir() . '/zend_cache_test_' . uniqid();
        mkdir($cacheDir);

        try {
            $backend = new Zend_Cache_Backend_File(['cache_dir' => $cacheDir]);
            $backend->save('data1', 'id1');
            $backend->save('data2', 'id2');
            $this->assertTrue($backend->clean(Zend_Cache::CLEANING_MODE_ALL));
            $this->assertFalse($backend->load('id1'));
            $this->assertFalse($backend->load('id2'));
        } finally {
            array_map('unlink', glob($cacheDir . '/*'));
            rmdir($cacheDir);
        }
    }
}
