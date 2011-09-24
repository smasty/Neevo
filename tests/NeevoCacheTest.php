<?php


/**
 * Tests for NeevoCache.
 */
class NeevoCacheTest extends PHPUnit_Framework_TestCase {


	private $filename = 'neevo.cache';


	public function getImplementations(){
		return array(
			array(new Neevo\Cache\MemoryStorage),
			array(new Neevo\Cache\SessionStorage),
			array(new Neevo\Cache\FileStorage($this->filename))
		);
	}


	/**
	 * @dataProvider getImplementations
	 */
	public function testBehaviour(Neevo\ICache $cache){
		$cache->store($k = 'key', $v = 'value');
		$this->assertEquals($v, $cache->fetch($k));

		if(method_exists($cache, 'flush')){
			$cache->flush();
			$this->assertNull($cache->fetch($k));
		}

		if($cache instanceof Neevo\Cache\FileStorage)
			unlink($this->filename);
	}


	public function testMemcache(){
		$memcache = new Memcache();
		$memcache->connect('localhost');
		$cache = new Neevo\Cache\MemcacheStorage($memcache);

		$cache->store($k = 'key', $v = 'value');
		$this->assertEquals($v, $cache->fetch($k));

		if(method_exists($cache, 'flush')){
			$cache->flush();
			$this->assertNull($cache->fetch($k));
		}
	}


}
