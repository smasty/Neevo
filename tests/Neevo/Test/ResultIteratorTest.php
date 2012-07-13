<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2012 Smasty (http://smasty.net)
 *
 */

namespace Neevo\Test;

use Neevo\Connection;
use Neevo\Result;


class ResultIteratorTest extends \PHPUnit_Framework_TestCase {

	/** @var Result */
	private $result, $result2;


	protected function setUp(){
		$this->result = new Result(new Connection('driver=Dummy'), 'foo');
		$this->result2 = new Result(new Connection('driver=Dummy&unbuffered=true'), 'foo');
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
		$iterator->seek(2);
		$this->assertTrue($iterator->valid());
		$this->assertEquals(2, $iterator->key());
	}


	public function testSeekUnbuffered(){
		$this->setExpectedException('Neevo\\DriverException', 'Cannot seek on unbuffered result.');
		$iterator = $this->result2->getIterator();
		$iterator->rewind();
		$iterator->seek(2);
	}


	public function testSeekOutOfRange(){
		$this->setExpectedException('OutOfRangeException', 'Cannot seek to offset');
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


	public function testValidDoubleCall(){
		$iterator = $this->result->getIterator();
		$iterator->rewind();
		$this->assertTrue($iterator->valid());
		$c = $iterator->current();
		$this->assertTrue($iterator->valid());
		$this->assertEquals($c->toArray(), $iterator->current()->toArray());
	}


}
