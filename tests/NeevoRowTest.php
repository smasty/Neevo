<?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for NeevoRow.
 */
class NeevoRowTest extends PHPUnit_Framework_TestCase {


	/** @var NeevoResult */
	private $result;

	/** @var NeevoRow */
	private $row;


	protected function setUp(){
		$this->result = new NeevoResult(new NeevoConnection('driver=Dummy'), 'author');
		$this->row = new NeevoRow($this->result->getConnection()->getDriver()->getRow(0), $this->result);
	}


	protected function tearDown(){
		unset($this->row, $this->result);
	}


	public function testArrayAccess(){
		if(isset($this->row['mail'])){
			$this->row['mail'] = $m = 'john.doe@email.tld';
		}
		A::assertEquals($m, $this->row['mail']);

		unset($this->row['mail']);
		A::assertNull($this->row['mail']);
	}


	public function testCount(){
		A::assertEquals(3, count($this->row));
	}


	public function testGetIterator(){
		A::assertInstanceOf('ArrayIterator', $this->row->getIterator());
	}


	public function testUpdate(){
		$this->row['id'] = 5;
		A::assertEquals(1, $this->row->update());
	}

	public function testDelete(){
		A::assertEquals(1, $this->row->delete());
	}


}
