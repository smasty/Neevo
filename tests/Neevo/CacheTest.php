<?php

use Neevo\Cache\CacheInterface;
use Neevo\Cache\FileStorage;
use Neevo\Cache\MemcacheStorage;
use Neevo\Cache\MemoryStorage;
use Neevo\Cache\SessionStorage;


/**
 * Tests for NeevoCache.
 */
class CacheTest extends PHPUnit_Framework_TestCase {


	private $filename = 'neevo.cache';


	public function getImplementations(){
		return array(
			array(new MemoryStorage),
			array(new SessionStorage),
			array(new FileStorage($this->filename))
		);
	}


	/**
	 * @dataProvider getImplementations
	 */
	public function testBehaviour(CacheInterface $cache){
		$cache->store($k = 'key', $v = 'value');
		$this->assertEquals($v, $cache->fetch($k));

		if(method_exists($cache, 'flush')){
			$cache->flush();
			$this->assertNull($cache->fetch($k));
		}

		if($cache instanceof FileStorage)
			unlink($this->filename);
	}


	public function testMemcache(){
		if(!class_exists('Memcache'))
			$this->markTestSkipped('Memcache extension not available.');

		$memcache = new Memcache;
		$memcache->connect('localhost');
		$cache = new MemcacheStorage($memcache);

		$cache->store($k = 'key', $v = 'value');
		$this->assertEquals($v, $cache->fetch($k));

		if(method_exists($cache, 'flush')){
			$cache->flush();
			$this->assertNull($cache->fetch($k));
		}
	}


}
