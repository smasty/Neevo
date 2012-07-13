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
use Neevo\Row;


class RowTest extends \PHPUnit_Framework_TestCase {


	/** @var Result */
	private $result;

	/** @var Row */
	private $row;


	protected function setUp(){
		$this->result = new Result(new Connection('driver=Dummy'), 'author');
		$this->row = new Row($this->result->getConnection()->getDriver()->getRow(0), $this->result);
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
