<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Cache
 * @subpackage Zend_Cache_Backend
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * SQLite3-based cache backend.
 *
 * @package    Zend_Cache
 * @subpackage Zend_Cache_Backend
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Cache_Backend_Sqlite extends Zend_Cache_Backend implements Zend_Cache_Backend_ExtendedInterface
{
    /**
     * Available options
     *
     * =====> (string) cache_db_complete_path :
     * - the complete path (filename included) of the SQLITE database
     *
     * ====> (int) automatic_vacuum_factor :
     * - Disable / Tune the automatic vacuum process
     * - The automatic vacuum process defragments the database file (and makes it smaller)
     *   when a clean() or delete() is called
     *     0               => no automatic vacuum
     *     1               => systematic vacuum (when delete() or clean() methods are called)
     *     x (integer) > 1 => automatic vacuum randomly 1 times on x clean() or delete()
     *
     * @var array Available options
     */
    protected $_options = array(
        'cache_db_complete_path' => null,
        'automatic_vacuum_factor' => 10
    );

    /**
     * @var ?SQLite3
     */
    private ?SQLite3 $_db = null;

    /**
     * Boolean to store if the structure has been checked or not
     *
     * @var boolean
     */
    private bool $_structureChecked = false;

    /**
     * Constructor
     *
     * @param  array $options Associative array of options
     * @throws Zend_Cache_Exception
     */
    public function __construct(array $options = array())
    {
        parent::__construct($options);
        if ($this->_options['cache_db_complete_path'] === null) {
            Zend_Cache::throwException('cache_db_complete_path option has to set');
        }
        if (!extension_loaded('sqlite3')) {
            Zend_Cache::throwException("Cannot use SQLite storage because the 'sqlite3' extension is not loaded in the current PHP environment");
        }
        $this->_getConnection();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->_db !== null) {
            $this->_db->close();
            $this->_db = null;
        }
    }

    /**
     * Test if a cache is available for the given id and (if yes) return it (false else)
     *
     * @param  string  $id                     Cache id
     * @param  boolean $doNotTestCacheValidity If set to true, the cache validity won't be tested
     * @return string|false Cached datas
     */
    public function load($id, $doNotTestCacheValidity = false)
    {
        $this->_checkAndBuildStructure();
        $sql = "SELECT content FROM cache WHERE id=:id";
        if (!$doNotTestCacheValidity) {
            $sql .= " AND (expire=0 OR expire>" . time() . ')';
        }
        $stmt = $this->_db->prepare($sql);
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            return $row['content'];
        }
        return false;
    }

    /**
     * Test if a cache is available or not (for the given id)
     *
     * @param string $id Cache id
     * @return mixed|false (a cache is not available) or "last modified" timestamp (int) of the available cache record
     */
    public function test($id)
    {
        $this->_checkAndBuildStructure();
        $stmt = $this->_db->prepare("SELECT lastModified FROM cache WHERE id=:id AND (expire=0 OR expire>" . time() . ')');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            return (int) $row['lastModified'];
        }
        return false;
    }

    /**
     * Save some string datas into a cache record
     *
     * Note : $data is always "string" (serialization is done by the
     * core not by the backend)
     *
     * @param  string $data             Datas to cache
     * @param  string $id               Cache id
     * @param  array  $tags             Array of strings, the cache record will be tagged by each string entry
     * @param  int    $specificLifetime If != false, set a specific lifetime for this cache record (null => infinite lifetime)
     * @return boolean True if no problem
     */
    public function save($data, $id, $tags = array(), $specificLifetime = false)
    {
        $this->_checkAndBuildStructure();
        $lifetime = $this->getLifetime($specificLifetime);
        $mktime = time();
        if ($lifetime === null) {
            $expire = 0;
        } else {
            $expire = $mktime + $lifetime;
        }

        $this->_db->exec("BEGIN");

        $stmt = $this->_db->prepare("DELETE FROM cache WHERE id=:id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->execute();

        $stmt = $this->_db->prepare("INSERT INTO cache (id, content, lastModified, expire) VALUES (:id, :content, :mtime, :expire)");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':content', $data, SQLITE3_BLOB);
        $stmt->bindValue(':mtime', $mktime, SQLITE3_INTEGER);
        $stmt->bindValue(':expire', $expire, SQLITE3_INTEGER);
        $res = $stmt->execute();

        if (!$res) {
            $this->_db->exec("ROLLBACK");
            $this->_log("Zend_Cache_Backend_Sqlite::save() : impossible to store the cache id=$id");
            return false;
        }

        $result = true;
        foreach ($tags as $tag) {
            $result = $this->_registerTag($id, $tag) && $result;
        }

        $this->_db->exec("COMMIT");
        return $result;
    }

    /**
     * Remove a cache record
     *
     * @param  string $id Cache id
     * @return boolean True if no problem
     */
    public function remove($id)
    {
        $this->_checkAndBuildStructure();

        $stmt = $this->_db->prepare("SELECT COUNT(*) AS nbr FROM cache WHERE id=:id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $exists = $row && (int) $row['nbr'] > 0;

        $stmt = $this->_db->prepare("DELETE FROM cache WHERE id=:id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $result2 = $stmt->execute();

        $stmt = $this->_db->prepare("DELETE FROM tag WHERE id=:id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $result3 = $stmt->execute();

        $this->_automaticVacuum();
        return ($exists && $result2 && $result3);
    }

    /**
     * Clean some cache records
     *
     * @param  string $mode Clean mode
     * @param  array  $tags Array of tags
     * @return boolean True if no problem
     */
    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = array())
    {
        $this->_checkAndBuildStructure();
        $return = $this->_clean($mode, $tags);
        $this->_automaticVacuum();
        return $return;
    }

    /**
     * Return an array of stored cache ids
     *
     * @return array array of stored cache ids (string)
     */
    public function getIds()
    {
        $this->_checkAndBuildStructure();
        $result = array();
        $res = $this->_db->query("SELECT id FROM cache WHERE (expire=0 OR expire>" . time() . ")");
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $result[] = $row['id'];
        }
        return $result;
    }

    /**
     * Return an array of stored tags
     *
     * @return array array of stored tags (string)
     */
    public function getTags()
    {
        $this->_checkAndBuildStructure();
        $result = array();
        $res = $this->_db->query("SELECT DISTINCT(name) AS name FROM tag");
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $result[] = $row['name'];
        }
        return $result;
    }

    /**
     * Return an array of stored cache ids which match given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param array $tags array of tags
     * @return array array of matching cache ids (string)
     */
    public function getIdsMatchingTags($tags = array())
    {
        $first = true;
        $ids = array();
        foreach ($tags as $tag) {
            $stmt = $this->_db->prepare("SELECT DISTINCT(id) AS id FROM tag WHERE name=:name");
            $stmt->bindValue(':name', $tag, SQLITE3_TEXT);
            $res = $stmt->execute();
            if (!$res) {
                return array();
            }
            $ids2 = array();
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $ids2[] = $row['id'];
            }
            if ($first) {
                $ids = $ids2;
                $first = false;
            } else {
                $ids = array_intersect($ids, $ids2);
            }
        }
        return array_values($ids);
    }

    /**
     * Return an array of stored cache ids which don't match given tags
     *
     * In case of multiple tags, a logical OR is made between tags
     *
     * @param array $tags array of tags
     * @return array array of not matching cache ids (string)
     */
    public function getIdsNotMatchingTags($tags = array())
    {
        $res = $this->_db->query("SELECT id FROM cache");
        $result = array();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $id = $row['id'];
            $matching = false;
            foreach ($tags as $tag) {
                $stmt = $this->_db->prepare("SELECT COUNT(*) AS nbr FROM tag WHERE name=:name AND id=:id");
                $stmt->bindValue(':name', $tag, SQLITE3_TEXT);
                $stmt->bindValue(':id', $id, SQLITE3_TEXT);
                $tagRes = $stmt->execute();
                $tagRow = $tagRes->fetchArray(SQLITE3_ASSOC);
                if ($tagRow && (int) $tagRow['nbr'] > 0) {
                    $matching = true;
                    break;
                }
            }
            if (!$matching) {
                $result[] = $id;
            }
        }
        return $result;
    }

    /**
     * Return an array of stored cache ids which match any given tags
     *
     * In case of multiple tags, a logical OR is made between tags
     *
     * @param array $tags array of tags
     * @return array array of any matching cache ids (string)
     */
    public function getIdsMatchingAnyTags($tags = array())
    {
        $ids = array();
        foreach ($tags as $tag) {
            $stmt = $this->_db->prepare("SELECT DISTINCT(id) AS id FROM tag WHERE name=:name");
            $stmt->bindValue(':name', $tag, SQLITE3_TEXT);
            $res = $stmt->execute();
            if (!$res) {
                return array();
            }
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $ids[$row['id']] = true;
            }
        }
        return array_keys($ids);
    }

    /**
     * Return the filling percentage of the backend storage
     *
     * @throws Zend_Cache_Exception
     * @return int integer between 0 and 100
     */
    public function getFillingPercentage()
    {
        $dir = dirname($this->_options['cache_db_complete_path']);
        $free = disk_free_space($dir);
        $total = disk_total_space($dir);
        if ($total == 0) {
            Zend_Cache::throwException('can\'t get disk_total_space');
        }
        if ($free >= $total) {
            return 100;
        }
        return (int) (100. * ($total - $free) / $total);
    }

    /**
     * Return an array of metadatas for the given cache id
     *
     * @param string $id cache id
     * @return array|false array of metadatas (false if the cache id is not found)
     */
    public function getMetadatas($id)
    {
        $this->_checkAndBuildStructure();
        $tags = array();
        $stmt = $this->_db->prepare("SELECT name FROM tag WHERE id=:id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $res = $stmt->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $tags[] = $row['name'];
        }

        $stmt = $this->_db->prepare("SELECT lastModified, expire FROM cache WHERE id=:id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            return false;
        }
        return array(
            'tags' => $tags,
            'mtime' => (int) $row['lastModified'],
            'expire' => (int) $row['expire']
        );
    }

    /**
     * Give (if possible) an extra lifetime to the given cache id
     *
     * @param string $id cache id
     * @param int $extraLifetime
     * @return boolean true if ok
     */
    public function touch($id, $extraLifetime)
    {
        $this->_checkAndBuildStructure();
        $stmt = $this->_db->prepare("SELECT expire FROM cache WHERE id=:id AND (expire=0 OR expire>" . time() . ')');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            return false;
        }
        $newExpire = (int) $row['expire'] + $extraLifetime;
        $stmt = $this->_db->prepare("UPDATE cache SET lastModified=:mtime, expire=:expire WHERE id=:id");
        $stmt->bindValue(':mtime', time(), SQLITE3_INTEGER);
        $stmt->bindValue(':expire', $newExpire, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        return (bool) $stmt->execute();
    }

    /**
     * Return an associative array of capabilities (booleans) of the backend
     *
     * @return array associative of with capabilities
     */
    public function getCapabilities()
    {
        return array(
            'automatic_cleaning' => true,
            'tags' => true,
            'expired_read' => true,
            'priority' => false,
            'infinite_lifetime' => true,
            'get_list' => true
        );
    }

    /**
     * PUBLIC METHOD FOR UNIT TESTING ONLY !
     *
     * Force a cache record to expire
     *
     * @param string $id Cache id
     */
    public function ___expire($id)
    {
        $time = time() - 1;
        $stmt = $this->_db->prepare("UPDATE cache SET lastModified=:mtime, expire=:expire WHERE id=:id");
        $stmt->bindValue(':mtime', $time, SQLITE3_INTEGER);
        $stmt->bindValue(':expire', $time, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->execute();
    }

    /**
     * Return the connection
     *
     * @throws Zend_Cache_Exception
     * @return SQLite3
     */
    private function _getConnection(): SQLite3
    {
        if ($this->_db !== null) {
            return $this->_db;
        }
        try {
            $this->_db = new SQLite3($this->_options['cache_db_complete_path']);
            $this->_db->enableExceptions(true);
        } catch (\Exception $e) {
            Zend_Cache::throwException("Impossible to open " . $this->_options['cache_db_complete_path'] . " cache DB file", $e);
        }
        return $this->_db;
    }

    /**
     * Deal with the automatic vacuum process
     */
    private function _automaticVacuum(): void
    {
        if ($this->_options['automatic_vacuum_factor'] > 0) {
            $rand = rand(1, $this->_options['automatic_vacuum_factor']);
            if ($rand == 1) {
                $this->_db->exec('VACUUM');
            }
        }
    }

    /**
     * Register a cache id with the given tag
     *
     * @param  string $id  Cache id
     * @param  string $tag Tag
     * @return boolean True if no problem
     */
    private function _registerTag(string $id, string $tag): bool
    {
        $stmt = $this->_db->prepare("DELETE FROM tag WHERE name=:name AND id=:id");
        $stmt->bindValue(':name', $tag, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->execute();

        $stmt = $this->_db->prepare("INSERT INTO tag (name, id) VALUES (:name, :id)");
        $stmt->bindValue(':name', $tag, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $res = $stmt->execute();
        if (!$res) {
            $this->_log("Zend_Cache_Backend_Sqlite::_registerTag() : impossible to register tag=$tag on id=$id");
            return false;
        }
        return true;
    }

    /**
     * Build the database structure
     */
    private function _buildStructure(): void
    {
        $this->_db->exec('DROP INDEX IF EXISTS tag_id_index');
        $this->_db->exec('DROP INDEX IF EXISTS tag_name_index');
        $this->_db->exec('DROP INDEX IF EXISTS cache_id_expire_index');
        $this->_db->exec('DROP TABLE IF EXISTS version');
        $this->_db->exec('DROP TABLE IF EXISTS cache');
        $this->_db->exec('DROP TABLE IF EXISTS tag');
        $this->_db->exec('CREATE TABLE version (num INTEGER PRIMARY KEY)');
        $this->_db->exec('CREATE TABLE cache (id TEXT PRIMARY KEY, content BLOB, lastModified INTEGER, expire INTEGER)');
        $this->_db->exec('CREATE TABLE tag (name TEXT, id TEXT)');
        $this->_db->exec('CREATE INDEX tag_id_index ON tag(id)');
        $this->_db->exec('CREATE INDEX tag_name_index ON tag(name)');
        $this->_db->exec('CREATE INDEX cache_id_expire_index ON cache(id, expire)');
        $this->_db->exec('INSERT INTO version (num) VALUES (1)');
    }

    /**
     * Check if the database structure is ok (with the good version)
     *
     * @return boolean True if ok
     */
    private function _checkStructureVersion(): bool
    {
        try {
            $res = $this->_db->query("SELECT num FROM version");
        } catch (\Exception $e) {
            return false;
        }
        if (!$res) {
            return false;
        }
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            return false;
        }
        if ((int) $row['num'] !== 1) {
            $this->_log('Zend_Cache_Backend_Sqlite::_checkStructureVersion() : old cache structure version detected => the cache is going to be dropped');
            return false;
        }
        return true;
    }

    /**
     * Clean some cache records (internal implementation)
     *
     * @param  string $mode Clean mode
     * @param  array  $tags Array of tags
     * @return boolean True if no problem
     */
    private function _clean(string $mode = Zend_Cache::CLEANING_MODE_ALL, array $tags = array()): bool
    {
        switch ($mode) {
            case Zend_Cache::CLEANING_MODE_ALL:
                $this->_db->exec('DELETE FROM cache');
                $this->_db->exec('DELETE FROM tag');
                return true;
            case Zend_Cache::CLEANING_MODE_OLD:
                $mktime = time();
                $this->_db->exec("DELETE FROM tag WHERE id IN (SELECT id FROM cache WHERE expire>0 AND expire<=$mktime)");
                $this->_db->exec("DELETE FROM cache WHERE expire>0 AND expire<=$mktime");
                return true;
            case Zend_Cache::CLEANING_MODE_MATCHING_TAG:
                $ids = $this->getIdsMatchingTags($tags);
                $result = true;
                foreach ($ids as $id) {
                    $result = $this->remove($id) && $result;
                }
                return $result;
            case Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG:
                $ids = $this->getIdsNotMatchingTags($tags);
                $result = true;
                foreach ($ids as $id) {
                    $result = $this->remove($id) && $result;
                }
                return $result;
            case Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
                $ids = $this->getIdsMatchingAnyTags($tags);
                $result = true;
                foreach ($ids as $id) {
                    $result = $this->remove($id) && $result;
                }
                return $result;
            default:
                break;
        }
        return false;
    }

    /**
     * Check if the database structure is ok (with the good version), if no : build it
     *
     * @throws Zend_Cache_Exception
     */
    private function _checkAndBuildStructure(): void
    {
        if (!$this->_structureChecked) {
            if (!$this->_checkStructureVersion()) {
                $this->_buildStructure();
                if (!$this->_checkStructureVersion()) {
                    Zend_Cache::throwException("Impossible to build cache structure in " . $this->_options['cache_db_complete_path']);
                }
            }
            $this->_structureChecked = true;
        }
    }
}
