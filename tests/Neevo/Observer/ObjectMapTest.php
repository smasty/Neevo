<?php


/**
 * Tests for Neevo\Observer\ObjectMap.
 */
class ObjectMapTest extends PHPUnit_Framework_TestCase {

	/** @var Neevo\Observer\ObjectMap */
	private $map;


	protected function setUp(){
		$this->map = new Neevo\Observer\ObjectMap;
	}


	protected function tearDown(){
		unset($this->map);
	}


	public function testAttach(){
		$this->map->attach($o = new DummyObserver, $e = 1024);
		$this->assertEquals($o, $this->map->current());
		$this->assertEquals($e, $this->map->getEvent());
	}


	public function testDetach(){
		$this->map->attach($o = new DummyObserver, 0);
		$this->map->detach($o);
		$this->assertFalse($this->map->contains($o));
	}


	public function testCount(){
		$this->map->attach(new DummyObserver, 0);
		$this->map->attach(new DummyObserver, 0);
		$this->assertEquals(2, $this->map->count());
	}


	public function testRewind(){
		$this->map->attach($o1 = new DummyObserver, 0);
		$this->map->attach($o2 = new DummyObserver, 0);
		$this->map->rewind();
		$this->assertEquals($o1, $this->map->current());
	}


	public function testNext(){
		$this->map->attach($o1 = new DummyObserver, 0);
		$this->map->attach($o2 = new DummyObserver, 0);
		$this->map->rewind();
		$this->map->next();
		$this->assertEquals($o2, $this->map->current());
	}


	public function testValid(){
		$this->map->attach(new DummyObserver, 0);
		$this->assertTrue($this->map->valid());
	}


	public function testKey(){
		$this->assertEquals(0, $this->map->key());
	}


}
