<?php

use PHPUnit\Framework\TestCase;

class ZendCacheCoreTest extends TestCase
{
    private Zend_Cache_Core $cache;
    private Zend_Cache_Backend_Test $backend;

    protected function setUp(): void
    {
        $this->backend = new Zend_Cache_Backend_Test();
        $this->cache = new Zend_Cache_Core([
            'automatic_serialization' => false,
            'caching' => true,
            'write_control' => false,
        ]);
        $this->cache->setBackend($this->backend);
    }

    public function testGetOption(): void
    {
        $this->assertFalse($this->cache->getOption('write_control'));
        $this->assertTrue($this->cache->getOption('caching'));
        $this->assertSame(3600, $this->cache->getOption('lifetime'));
    }

    public function testSetOption(): void
    {
        $this->cache->setOption('caching', false);
        $this->assertFalse($this->cache->getOption('caching'));
    }

    public function testGetOptionThrowsOnInvalidName(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        $this->cache->getOption('nonexistent_option');
    }

    public function testGetBackend(): void
    {
        $this->assertSame($this->backend, $this->cache->getBackend());
    }

    public function testLoad(): void
    {
        $result = $this->cache->load('foo');
        $this->assertSame('foo', $result);
    }

    public function testLoadReturnsFalseForMiss(): void
    {
        $result = $this->cache->load('false');
        $this->assertFalse($result);
    }

    public function testLoadWithCachingDisabled(): void
    {
        $this->cache->setOption('caching', false);
        $this->assertFalse($this->cache->load('foo'));
    }

    public function testLoadWithAutomaticSerialization(): void
    {
        $cache = new Zend_Cache_Core(['automatic_serialization' => true]);
        $cache->setBackend($this->backend);
        $result = $cache->load('serialized');
        $this->assertSame(['foo'], $result);
    }

    public function testSave(): void
    {
        $result = $this->cache->save('data', 'testid');
        $this->assertTrue($result);
    }

    public function testSaveWithCachingDisabled(): void
    {
        $this->cache->setOption('caching', false);
        $this->assertTrue($this->cache->save('data', 'testid'));
    }

    public function testSaveThrowsOnNonStringWithoutSerialization(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        $this->cache->save(['array'], 'testid');
    }

    public function testSaveWithAutomaticSerialization(): void
    {
        $cache = new Zend_Cache_Core(['automatic_serialization' => true, 'write_control' => false]);
        $cache->setBackend($this->backend);
        $this->assertTrue($cache->save(['foo', 'bar'], 'testid'));
    }

    public function testTest(): void
    {
        $result = $this->cache->test('foo');
        $this->assertSame(123456, $result);
    }

    public function testTestReturnsFalseForMiss(): void
    {
        $this->assertFalse($this->cache->test('false'));
    }

    public function testTestWithCachingDisabled(): void
    {
        $this->cache->setOption('caching', false);
        $this->assertFalse($this->cache->test('foo'));
    }

    public function testRemove(): void
    {
        $this->assertTrue($this->cache->remove('testid'));
    }

    public function testRemoveWithCachingDisabled(): void
    {
        $this->cache->setOption('caching', false);
        $this->assertTrue($this->cache->remove('testid'));
    }

    public function testClean(): void
    {
        $this->assertTrue($this->cache->clean());
        $this->assertTrue($this->cache->clean(Zend_Cache::CLEANING_MODE_OLD));
        $this->assertTrue($this->cache->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, ['tag1']));
    }

    public function testCleanThrowsOnInvalidMode(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        $this->cache->clean('invalid_mode');
    }

    public function testCleanWithCachingDisabled(): void
    {
        $this->cache->setOption('caching', false);
        $this->assertTrue($this->cache->clean());
    }

    public function testCacheIdPrefix(): void
    {
        $cache = new Zend_Cache_Core(['cache_id_prefix' => 'prefix_']);
        $cache->setBackend($this->backend);
        $result = $cache->load('foo');
        $this->assertSame('foo', $result);
    }

    public function testSetLifetime(): void
    {
        $this->cache->setLifetime(7200);
        $this->assertSame(7200, $this->cache->getOption('lifetime'));
    }

    public function testSetLifetimeNull(): void
    {
        $this->cache->setOption('lifetime', 0);
        $this->assertNull($this->cache->getOption('lifetime'));
    }

    public function testGetIds(): void
    {
        $ids = $this->cache->getIds();
        $this->assertIsArray($ids);
        $this->assertContains('prefix_id1', $ids);
    }

    public function testGetTags(): void
    {
        $tags = $this->cache->getTags();
        $this->assertIsArray($tags);
        $this->assertContains('tag1', $tags);
    }

    public function testGetIdsMatchingTags(): void
    {
        $ids = $this->cache->getIdsMatchingTags(['tag1', 'tag2']);
        $this->assertContains('prefix_id1', $ids);
    }

    public function testGetIdsNotMatchingTags(): void
    {
        $ids = $this->cache->getIdsNotMatchingTags(['tag3', 'tag4']);
        $this->assertContains('prefix_id3', $ids);
    }

    public function testGetIdsMatchingAnyTags(): void
    {
        $ids = $this->cache->getIdsMatchingAnyTags(['tag5', 'tag6']);
        $this->assertContains('prefix_id5', $ids);
    }

    public function testGetFillingPercentage(): void
    {
        $this->assertSame(50, $this->cache->getFillingPercentage());
    }

    public function testGetMetadatas(): void
    {
        $this->assertFalse($this->cache->getMetadatas('testid'));
    }

    public function testTouch(): void
    {
        $this->assertTrue($this->cache->touch('testid', 100));
    }

    public function testConstructorThrowsOnInvalidOptions(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        new Zend_Cache_Core('not_an_array');
    }
}
