<?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for NeevoCache.
 */
class NeevoCacheTest extends PHPUnit_Framework_TestCase {


	protected function setUp(){

	}


	protected function tearDown(){
	}


	public function testDefaultCache(){
		$cache = new NeevoCache;
		$cache->store($k = 'key', $v = 'value');
		A::assertEquals($v, $cache->fetch($k));

		$cache->flush();
		A::assertNull($cache->fetch($k));
	}


	public function testSessionCache(){
		$_SESSION = array();

		$cache = new NeevoCacheSession;
		$cache->store($k = 'key', $v = 'value');
		A::assertEquals($v, $cache->fetch($k));

		$cache->flush();
		A::assertNull($cache->fetch($k));
	}


	public function testMemcacheCache(){
		$memcache = new Memcache;
		$memcache->connect('localhost');

		$cache = new NeevoCacheMemcache($memcache);
		$cache->store($k = 'key', $v = 'value');
		A::assertEquals($v, $cache->fetch($k));

		$cache->flush();
		A::assertNull($cache->fetch($k));
	}


	public function testFileCache(){
		$file = 'neevo.cache';
		
		$cache = new NeevoCacheFile($file);
		$cache->store($k = 'key', $v = 'value');
		A::assertEquals($v, $cache->fetch($k));

		$cache->flush();
		A::assertNull($cache->fetch($k));

		unlink($file);
	}


}
