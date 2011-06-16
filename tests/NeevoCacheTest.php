<?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for NeevoCache.
 */
class NeevoCacheTest extends PHPUnit_Framework_TestCase {


	public function getImplementations(){
		$memcache = new Memcache;
		$memcache->connect('localhost');

		return array(
			array(new NeevoCacheMemory),
			array(new NeevoCacheSession),
			array(new NeevoCacheMemcache($memcache))
		);
	}


	/**
	 * @dataProvider getImplementations
	 */
	public function testBaseBehaviour(INeevoCache $cache){
		$cache->store($k = 'key', $v = 'value');
		A::assertEquals($v, $cache->fetch($k));

		$cache->flush();
		A::assertNull($cache->fetch($k));
	}


	public function testNeevoCacheFile(){
		$filename = 'neevo.cache';
		$this->testBaseBehaviour(new NeevoCacheFile($filename));
		unlink($filename);
	}


}
