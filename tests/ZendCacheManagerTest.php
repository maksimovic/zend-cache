<?php

use PHPUnit\Framework\TestCase;

class ZendCacheManagerTest extends TestCase
{
    private Zend_Cache_Manager $manager;

    protected function setUp(): void
    {
        $this->manager = new Zend_Cache_Manager();
    }

    public function testHasCacheTemplate(): void
    {
        $this->assertTrue($this->manager->hasCacheTemplate('default'));
        $this->assertTrue($this->manager->hasCacheTemplate('page'));
        $this->assertTrue($this->manager->hasCacheTemplate('pagetag'));
        $this->assertFalse($this->manager->hasCacheTemplate('nonexistent'));
    }

    public function testSetAndGetCache(): void
    {
        $cache = Zend_Cache::factory('Core', 'BlackHole');
        $this->manager->setCache('test', $cache);
        $this->assertTrue($this->manager->hasCache('test'));
        $this->assertSame($cache, $this->manager->getCache('test'));
    }

    public function testGetCacheTemplate(): void
    {
        $template = $this->manager->getCacheTemplate('default');
        $this->assertIsArray($template);
        $this->assertSame('Core', $template['frontend']['name']);
        $this->assertSame('File', $template['backend']['name']);
    }

    public function testGetCacheTemplateReturnsNullForUnknown(): void
    {
        $this->assertNull($this->manager->getCacheTemplate('nonexistent'));
    }

    public function testSetCacheTemplate(): void
    {
        $this->manager->setCacheTemplate('custom', [
            'frontend' => ['name' => 'Core', 'options' => []],
            'backend' => ['name' => 'BlackHole', 'options' => []],
        ]);
        $this->assertTrue($this->manager->hasCacheTemplate('custom'));
    }

    public function testSetCacheTemplateThrowsOnInvalidOptions(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        $this->manager->setCacheTemplate('bad', 'not_an_array');
    }

    public function testSetTemplateOptions(): void
    {
        $this->manager->setTemplateOptions('default', [
            'frontend' => ['options' => ['lifetime' => 7200]],
        ]);
        $template = $this->manager->getCacheTemplate('default');
        $this->assertSame(7200, $template['frontend']['options']['lifetime']);
    }

    public function testSetTemplateOptionsThrowsOnInvalidOptions(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        $this->manager->setTemplateOptions('default', 'not_an_array');
    }

    public function testSetTemplateOptionsThrowsOnUnknownTemplate(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        $this->manager->setTemplateOptions('nonexistent', ['frontend' => []]);
    }

    public function testHasCacheReturnsTrueForTemplate(): void
    {
        $this->assertTrue($this->manager->hasCache('default'));
    }
}
