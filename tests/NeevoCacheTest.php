<?php


/**
 * Tests for NeevoCache.
 */
class NeevoCacheTest extends PHPUnit_Framework_TestCase {


	public function getImplementations(){
		return array(
			array(new NeevoCacheMemory),
			array(new NeevoCacheSession)
		);
	}


	/**
	 * @dataProvider getImplementations
	 */
	public function testBaseBehaviour(INeevoCache $cache){
		$cache->store($k = 'key', $v = 'value');
		$this->assertEquals($v, $cache->fetch($k));

		if(method_exists($cache, 'flush')){
			$cache->flush();
			$this->assertNull($cache->fetch($k));
		}
	}


	public function testNeevoCacheFile(){
		$filename = 'neevo.cache';
		$this->testBaseBehaviour(new NeevoCacheFile($filename));
		unlink($filename);
	}


	public function testNeevoCacheMemcache(){
		$memcache = new Memcache;
		$memcache->connect('localhost');
		$this->testBaseBehaviour(new NeevoCacheMemcache($memcache));

	}


}
