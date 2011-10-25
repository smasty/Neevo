<?php


/**
 * Tests for Neevo\Row.
 */
class RowTest extends PHPUnit_Framework_TestCase {


	/** @var Neevo\Result */
	private $result;

	/** @var Neevo\Row */
	private $row;


	protected function setUp(){
		$this->result = new Neevo\Result(new Neevo\Connection('driver=Dummy'), 'author');
		$this->row = new Neevo\Row($this->result->getConnection()->getDriver()->getRow(0), $this->result);
	}


	protected function tearDown(){
		unset($this->row, $this->result);
	}


	public function testArrayAccess(){
		if(isset($this->row['mail'])){
			$this->row['mail'] = $m = 'john.doe@email.tld';
		}
		$this->assertEquals($m, $this->row['mail']);

		unset($this->row['mail']);
		$this->assertNull($this->row['mail']);
	}


	public function testCount(){
		$this->assertEquals(3, count($this->row));
	}


	public function testGetIterator(){
		$this->assertInstanceOf('ArrayIterator', $this->row->getIterator());
	}


	public function testUpdate(){
		$this->row['id'] = 5;
		$this->assertEquals(1, $this->row->update());
	}

	public function testUpdateNotModified(){
		$this->assertEquals(0, $this->row->update());
	}

	public function testDelete(){
		$this->assertEquals(1, $this->row->delete());
	}

	public function testIsFrozen(){
		$this->assertFalse($this->row->isFrozen());
	}


}