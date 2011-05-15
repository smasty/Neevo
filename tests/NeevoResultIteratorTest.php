<?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for NeevoResultIterator.
 */
class NeevoResultIteratorTest extends PHPUnit_Framework_TestCase {

	/** @var NeevoResult */
	private $result, $result2;


	protected function setUp(){
		$this->result = new NeevoResult(new NeevoConnection('driver=Dummy'), 'foo');
		$this->result2 = new NeevoResult(new NeevoConnection('driver=Dummy&unbuffered=true'), 'foo');
	}


	protected function tearDown(){
		unset($this->result);
	}


	public function testIteration(){
		$rows = array();
		foreach($this->result as $key => $row){
			$rows[$key] = $row->toArray();
		}
		A::assertEquals($this->result->getConnection()->getDriver()->getRow(), $rows);
	}


	public function testCount(){
		A::assertEquals(3, count($this->result->getIterator()));
	}


	public function testSeek(){
		$iterator = $this->result->getIterator();
		$iterator->rewind();
		$iterator->seek(2);
		A::assertEquals('2', $iterator->current()->id);
	}


	/**
	 * @expectedException NeevoDriverException
	 */
	public function testSeekUnbuffered(){
		$iterator = $this->result2->getIterator();
		$iterator->rewind();
		$iterator->seek(2);
	}


	/**
	 * @expectedException OutOfBoundsException
	 */
	public function testSeekOutOfBounds(){
		$iterator = $this->result->getIterator();
		$iterator->rewind();
		$iterator->seek(50);
	}


	public function testDoubleRewind(){
		$iterator = $this->result->getIterator();
		$iterator->rewind();
		$c = $iterator->current();
		$iterator->rewind();
		A::assertEquals($c->toArray(), $iterator->current()->toArray());
	}


}
