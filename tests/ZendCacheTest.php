<?php

use PHPUnit\Framework\TestCase;

class ZendCacheTest extends TestCase
{
    public function testFactoryCreatesFileBackend(): void
    {
        $cache = Zend_Cache::factory(
            'Core',
            'File',
            ['automatic_serialization' => true],
            ['cache_dir' => sys_get_temp_dir()]
        );
        $this->assertInstanceOf(Zend_Cache_Core::class, $cache);
    }

    public function testFactoryWithBlackHoleBackend(): void
    {
        $cache = Zend_Cache::factory('Core', 'BlackHole');
        $this->assertInstanceOf(Zend_Cache_Core::class, $cache);
    }

    public function testFactoryWithTestBackend(): void
    {
        $cache = Zend_Cache::factory('Core', 'Test');
        $this->assertInstanceOf(Zend_Cache_Core::class, $cache);
    }

    public function testFactoryWithBackendObject(): void
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $cache = Zend_Cache::factory('Core', $backend);
        $this->assertInstanceOf(Zend_Cache_Core::class, $cache);
        $this->assertSame($backend, $cache->getBackend());
    }

    public function testFactoryWithFrontendObject(): void
    {
        $frontend = new Zend_Cache_Core();
        $cache = Zend_Cache::factory($frontend, 'BlackHole');
        $this->assertSame($frontend, $cache);
    }

    public function testFactoryThrowsOnInvalidBackend(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        Zend_Cache::factory('Core', new stdClass());
    }

    public function testFactoryThrowsOnInvalidFrontend(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        Zend_Cache::factory(123, 'BlackHole');
    }

    public function testThrowExceptionWithPrevious(): void
    {
        $previous = new RuntimeException('root');
        try {
            Zend_Cache::throwException('wrapped', $previous);
        } catch (Zend_Cache_Exception $e) {
            $this->assertSame('wrapped', $e->getMessage());
            $this->assertSame($previous, $e->getPrevious());
            return;
        }
        $this->fail('Expected Zend_Cache_Exception');
    }

    public function testCacheExceptionExtendsZendException(): void
    {
        $exception = new Zend_Cache_Exception('test');
        $this->assertInstanceOf(Zend_Exception::class, $exception);
    }

    public function testStandardFrontendsAndBackendsAreDefined(): void
    {
        $this->assertContains('Core', Zend_Cache::$standardFrontends);
        $this->assertContains('File', Zend_Cache::$standardBackends);
        $this->assertContains('File', Zend_Cache::$standardExtendedBackends);
    }

    public function testCleaningModeConstants(): void
    {
        $this->assertSame('all', Zend_Cache::CLEANING_MODE_ALL);
        $this->assertSame('old', Zend_Cache::CLEANING_MODE_OLD);
        $this->assertSame('matchingTag', Zend_Cache::CLEANING_MODE_MATCHING_TAG);
        $this->assertSame('notMatchingTag', Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG);
        $this->assertSame('matchingAnyTag', Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG);
    }
}
