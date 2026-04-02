<?php

use PHPUnit\Framework\TestCase;

class ZendCacheFrontendFileTest extends TestCase
{
    private string $masterFile;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/zend_cache_ff_' . uniqid();
        mkdir($this->cacheDir);
        $this->masterFile = tempnam(sys_get_temp_dir(), 'zend_master_');
        file_put_contents($this->masterFile, 'master');
        // ensure master file mtime is in the past so cache entries are newer
        touch($this->masterFile, time() - 2);
    }

    protected function tearDown(): void
    {
        @unlink($this->masterFile);
        $files = glob($this->cacheDir . '/*');
        if ($files) {
            array_map('unlink', $files);
        }
        @rmdir($this->cacheDir);
    }

    private function makeCache(array $extraOptions = []): Zend_Cache_Frontend_File
    {
        $options = array_merge([
            'master_files' => [$this->masterFile],
            'caching' => true,
            'write_control' => false,
        ], $extraOptions);
        $frontend = new Zend_Cache_Frontend_File($options);
        $frontend->setBackend(new Zend_Cache_Backend_File(['cache_dir' => $this->cacheDir]));
        return $frontend;
    }

    public function testSaveAndLoadValidCache(): void
    {
        $cache = $this->makeCache();
        $cache->save('cached data', 'key1');
        // master file not touched, cache should be valid
        $this->assertSame('cached data', $cache->load('key1'));
    }

    public function testCacheInvalidatedWhenMasterFileTouched(): void
    {
        $cache = $this->makeCache();
        $cache->save('cached data', 'key1');

        touch($this->masterFile, time() + 2);
        clearstatcache();

        $cache2 = $this->makeCache();
        $this->assertFalse($cache2->load('key1'));
    }

    public function testTestReturnsTimestampWhenValid(): void
    {
        $cache = $this->makeCache();
        $cache->save('data', 'key1');
        $result = $cache->test('key1');
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testTestReturnsFalseWhenMasterTouched(): void
    {
        $cache = $this->makeCache();
        $cache->save('data', 'key1');

        touch($this->masterFile, time() + 2);
        clearstatcache();

        $cache2 = $this->makeCache();
        $this->assertFalse($cache2->test('key1'));
    }

    public function testTestReturnsFalseForMiss(): void
    {
        $cache = $this->makeCache();
        $this->assertFalse($cache->test('nonexistent'));
    }

    public function testLoadWithDoNotTestValidity(): void
    {
        $cache = $this->makeCache();
        $cache->save('data', 'key1');

        touch($this->masterFile, time() + 2);
        clearstatcache();

        $cache2 = $this->makeCache();
        // with doNotTestCacheValidity = true, should still return data
        $this->assertSame('data', $cache2->load('key1', true));
    }

    public function testModeAnd(): void
    {
        $masterFile2 = tempnam(sys_get_temp_dir(), 'zend_master2_');
        file_put_contents($masterFile2, 'master2');
        touch($masterFile2, time() - 2);

        try {
            $cache = $this->makeCache([
                'master_files' => [$this->masterFile, $masterFile2],
                'master_files_mode' => Zend_Cache_Frontend_File::MODE_AND,
            ]);
            $cache->save('data', 'key1');

            // touch only one master file to be newer than cache
            touch($this->masterFile, time() + 2);
            clearstatcache();

            $cache2 = $this->makeCache([
                'master_files' => [$this->masterFile, $masterFile2],
                'master_files_mode' => Zend_Cache_Frontend_File::MODE_AND,
            ]);
            // MODE_AND: cache invalid only if ALL master files are newer — only one touched, so still valid
            $this->assertIsInt($cache2->test('key1'));
        } finally {
            @unlink($masterFile2);
        }
    }

    public function testModeOr(): void
    {
        $masterFile2 = tempnam(sys_get_temp_dir(), 'zend_master2_');
        file_put_contents($masterFile2, 'master2');
        touch($masterFile2, time() - 2);

        try {
            $cache = $this->makeCache([
                'master_files' => [$this->masterFile, $masterFile2],
                'master_files_mode' => Zend_Cache_Frontend_File::MODE_OR,
            ]);
            $cache->save('data', 'key1');

            // touch only one master file to be newer than cache
            touch($this->masterFile, time() + 2);
            clearstatcache();

            $cache2 = $this->makeCache([
                'master_files' => [$this->masterFile, $masterFile2],
                'master_files_mode' => Zend_Cache_Frontend_File::MODE_OR,
            ]);
            // MODE_OR: cache invalid if ANY master file is newer
            $this->assertFalse($cache2->test('key1'));
        } finally {
            @unlink($masterFile2);
        }
    }

    public function testConstructorThrowsWithoutMasterFiles(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        new Zend_Cache_Frontend_File([]);
    }

    public function testConstructorThrowsOnMissingMasterFile(): void
    {
        $this->expectException(Zend_Cache_Exception::class);
        new Zend_Cache_Frontend_File([
            'master_files' => ['/nonexistent/file'],
        ]);
    }

    public function testIgnoreMissingMasterFiles(): void
    {
        $cache = new Zend_Cache_Frontend_File([
            'ignore_missing_master_files' => true,
            'master_files' => ['/nonexistent/file'],
        ]);
        $this->assertInstanceOf(Zend_Cache_Frontend_File::class, $cache);
    }

    public function testSetMasterFile(): void
    {
        $cache = $this->makeCache();
        $newMaster = tempnam(sys_get_temp_dir(), 'zend_newmaster_');
        file_put_contents($newMaster, 'new');
        try {
            $cache->setMasterFile($newMaster);
            $this->assertInstanceOf(Zend_Cache_Frontend_File::class, $cache);
        } finally {
            @unlink($newMaster);
        }
    }
}
