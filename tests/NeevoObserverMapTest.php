<?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for NeevoObserverMap.
 */
class NeevoObserverMapTest extends PHPUnit_Framework_TestCase {

	/** @var NeevoObserverMap */
	private $map;


	protected function setUp(){
		$this->map = new NeevoObserverMap;
	}


	protected function tearDown(){
		unset($this->map);
	}


	public function testAttach(){
		$this->map->attach($o = new DummyObserver, $e = 1024);
		A::assertEquals($o, $this->map->current());
		A::assertEquals($e, $this->map->getEvent());
	}


	public function testDetach(){
		$this->map->attach($o = new DummyObserver, 0);
		$this->map->detach($o);
		A::assertFalse($this->map->contains($o));
	}


	public function testCount(){
		$this->map->attach(new DummyObserver, 0);
		$this->map->attach(new DummyObserver, 0);
		A::assertEquals(2, $this->map->count());
	}


	public function testRewind(){
		$this->map->attach($o1 = new DummyObserver, 0);
		$this->map->attach($o2 = new DummyObserver, 0);
		$this->map->rewind();
		A::assertEquals($o1, $this->map->current());
	}


	public function testNext(){
		$this->map->attach($o1 = new DummyObserver, 0);
		$this->map->attach($o2 = new DummyObserver, 0);
		$this->map->rewind();
		$this->map->next();
		A::assertEquals($o2, $this->map->current());
	}


	public function testValid(){
		$this->map->attach(new DummyObserver, 0);
		A::assertTrue($this->map->valid());
	}


	public function testKey(){
		A::assertEquals(0, $this->map->key());
	}


}
