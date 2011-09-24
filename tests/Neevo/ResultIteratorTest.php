<?php


/**
 * Tests for Neevo\ResultIterator.
 */
class ResultIteratorTest extends PHPUnit_Framework_TestCase {

	/** @var Neevo\Result */
	private $result, $result2;


	protected function setUp(){
		$this->result = new Neevo\Result(new Neevo\Connection('driver=Dummy'), 'foo');
		$this->result2 = new Neevo\Result(new Neevo\Connection('driver=Dummy&unbuffered=true'), 'foo');
	}


	protected function tearDown(){
		unset($this->result);
	}


	public function testIteration(){
		$rows = array();
		foreach($this->result as $key => $row){
			$rows[$key] = $row->toArray();
		}
		$this->assertEquals($this->result->getConnection()->getDriver()->getRow(), $rows);
	}


	public function testCount(){
		$this->assertEquals(3, count($this->result->getIterator()));
	}


	public function testSeek(){
		$iterator = $this->result->getIterator();
		$iterator->rewind();
		$iterator->seek(1);
		$this->assertTrue($iterator->valid());
		$this->assertEquals('2', $iterator->current()->id);
	}


	/**
	 * @expectedException Neevo\DriverException
	 */
	public function testSeekUnbuffered(){
		$iterator = $this->result2->getIterator();
		$iterator->rewind();
		$iterator->seek(2);
	}


	/**
	 * @expectedException OutOfRangeException
	 */
	public function testSeekOutOfRange(){
		$iterator = $this->result->getIterator();
		$iterator->rewind();
		$iterator->seek(50);
	}


	public function testDoubleRewind(){
		$iterator = $this->result->getIterator();
		$iterator->rewind();
		$this->assertTrue($iterator->valid());
		$c = $iterator->current();
		$iterator->rewind();
		$this->assertTrue($iterator->valid());
		$this->assertEquals($c->toArray(), $iterator->current()->toArray());
	}


	public function testSeekBeforeIteration(){
		$this->result->seek(1);
		$ids = array();
		foreach($this->result as $row){
			$ids[] = (int) $row['id'];
		}
		$this->assertEquals(array(2, 3), $ids);
	}


}
