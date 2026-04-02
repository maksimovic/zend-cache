<?php

use PHPUnit\Framework\TestCase;

class ZendCacheBackendLibmemcachedTest extends TestCase
{
    private Zend_Cache_Backend_Libmemcached $backend;

    protected function setUp(): void
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('ext-memcached is not installed');
        }

        $this->backend = new Zend_Cache_Backend_Libmemcached([
            'servers' => [['host' => '127.0.0.1', 'port' => 11211]],
        ]);

        // flush before each test
        $this->backend->clean(Zend_Cache::CLEANING_MODE_ALL);
    }

    public function testSaveAndLoad(): void
    {
        $this->assertTrue($this->backend->save('hello', 'test_id'));
        $this->assertSame('hello', $this->backend->load('test_id'));
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

    public function testCleanAll(): void
    {
        $this->backend->save('data1', 'key1');
        $this->backend->save('data2', 'key2');
        $this->backend->clean(Zend_Cache::CLEANING_MODE_ALL);
        $this->assertFalse($this->backend->load('key1'));
        $this->assertFalse($this->backend->load('key2'));
    }

    public function testGetMetadatas(): void
    {
        $this->backend->save('data', 'key1');
        $meta = $this->backend->getMetadatas('key1');
        $this->assertIsArray($meta);
        $this->assertArrayHasKey('expire', $meta);
        $this->assertArrayHasKey('mtime', $meta);
        $this->assertArrayHasKey('tags', $meta);
        $this->assertSame([], $meta['tags']);
    }

    public function testGetMetadatasNonExistent(): void
    {
        $this->assertFalse($this->backend->getMetadatas('nonexistent'));
    }

    public function testTouch(): void
    {
        $this->backend->save('data', 'key1', [], 100);
        $meta1 = $this->backend->getMetadatas('key1');
        $this->assertTrue($this->backend->touch('key1', 200));
        $meta2 = $this->backend->getMetadatas('key1');
        $this->assertGreaterThanOrEqual($meta1['expire'], $meta2['expire']);
    }

    public function testTouchNonExistent(): void
    {
        $this->assertFalse($this->backend->touch('nonexistent', 100));
    }

    public function testGetCapabilities(): void
    {
        $caps = $this->backend->getCapabilities();
        $this->assertFalse($caps['automatic_cleaning']);
        $this->assertFalse($caps['tags']);
        $this->assertFalse($caps['expired_read']);
        $this->assertFalse($caps['priority']);
        $this->assertFalse($caps['infinite_lifetime']);
        $this->assertFalse($caps['get_list']);
    }

    public function testGetFillingPercentage(): void
    {
        $pct = $this->backend->getFillingPercentage();
        $this->assertIsInt($pct);
        $this->assertGreaterThanOrEqual(0, $pct);
        $this->assertLessThanOrEqual(100, $pct);
    }

    public function testIsAutomaticCleaningAvailable(): void
    {
        $this->assertFalse($this->backend->isAutomaticCleaningAvailable());
    }

    public function testGetIdsReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->backend->getIds());
    }

    public function testGetTagsReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->backend->getTags());
    }
}
