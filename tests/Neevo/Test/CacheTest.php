<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2012 Smasty (http://smasty.net)
 *
 */

namespace Neevo\Test;

use Memcache;
use Neevo\Cache\FileStorage;
use Neevo\Cache\MemcacheStorage;
use Neevo\Cache\MemoryStorage;
use Neevo\Cache\SessionStorage;
use Neevo\Cache\StorageInterface;

class CacheTest extends \PHPUnit_Framework_TestCase
{


    private $filename = 'neevo.cache';


    public function getImplementations()
    {
        return array(
            array(new MemoryStorage),
            array(new SessionStorage),
            array(new FileStorage($this->filename))
        );
    }


    /**
     * @dataProvider getImplementations
     */
    public function testBehaviour(StorageInterface $cache)
    {
        $cache->store($k = 'key', $v = 'value');
        $this->assertEquals($v, $cache->fetch($k));

        if (method_exists($cache, 'flush')) {
            $cache->flush();
            $this->assertNull($cache->fetch($k));
        }

        if ($cache instanceof FileStorage) {
            unlink($this->filename);
        }
    }


    public function testMemcache()
    {
        if (!class_exists('Memcache')) {
            $this->markTestSkipped('Memcache extension not available.');
        }

        $memcache = new Memcache;
        $memcache->connect('localhost');
        $cache = new MemcacheStorage($memcache);

        $cache->store($k = 'key', $v = 'value');
        $this->assertEquals($v, $cache->fetch($k));

        if (method_exists($cache, 'flush')) {
            $cache->flush();
            $this->assertNull($cache->fetch($k));
        }
    }
}
