<?php


/**
 * Tests for NeevoCache.
 */
class NeevoCacheTest extends PHPUnit_Framework_TestCase {


	private $filename = 'neevo.cache';


	public function getImplementations(){
		return array(
			array(new NeevoCacheMemory),
			array(new NeevoCacheSession),
			array(new NeevoCacheFile($this->filename))
		);
	}


	/**
	 * @dataProvider getImplementations
	 */
	public function testBehaviour(INeevoCache $cache){
		$cache->store($k = 'key', $v = 'value');
		$this->assertEquals($v, $cache->fetch($k));

		if(method_exists($cache, 'flush')){
			$cache->flush();
			$this->assertNull($cache->fetch($k));
		}

		if($cache instanceof NeevoCacheFile)
			unlink($this->filename);
	}


	public function testMemcache(){
		$memcache = new Memcache();
		$memcache->connect('localhost');
		$cache = new NeevoCacheMemcache($memcache);

		$cache->store($k = 'key', $v = 'value');
		$this->assertEquals($v, $cache->fetch($k));

		if(method_exists($cache, 'flush')){
			$cache->flush();
			$this->assertNull($cache->fetch($k));
		}
	}


}
