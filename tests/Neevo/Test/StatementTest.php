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
use Neevo\Manager;
use Neevo\Statement;
use ReflectionMethod;
use ReflectionProperty;


class StatementTest extends \PHPUnit_Framework_TestCase {


	/** @var Connection */
	private $connection;


	protected function setUp(){
		$this->connection = new Connection(array(
				'driver' => 'Dummy'
			));
	}


	protected function tearDown(){
		unset($this->stmt);
	}


	public function testCreateUpdate(){
		$stmt = Statement::createUpdate($this->connection, $s = 'table', $d = array('column' => 'value'));
		$this->assertEquals($d, $stmt->getValues());
		$this->assertEquals($s, $stmt->getTable());
		$this->assertEquals(Manager::STMT_UPDATE, $stmt->getType());
	}


	public function testCreateInsert(){
		$stmt = Statement::createInsert($this->connection, $s = 'table', $d = array('column' => 'value'));
		$this->assertEquals($d, $stmt->getValues());
		$this->assertEquals($s, $stmt->getTable());
		$this->assertEquals(Manager::STMT_INSERT, $stmt->getType());
	}


	public function testCreateDelete(){
		$stmt = Statement::createDelete($this->connection, $s = 'table');
		$this->assertEquals($s, $stmt->getTable());
		$this->assertEquals(Manager::STMT_DELETE, $stmt->getType());
	}


	public function testRun(){
		$stmt = Statement::createDelete($this->connection, 'table');
		$this->assertInstanceOf('DummyResult', $stmt->run());
	}


	public function testInsertId(){
		$stmt = Statement::createInsert($this->connection, 'table', array('column', 'value'));
		$this->assertEquals(4, $stmt->insertId());
	}


	public function testInsertIdException(){
		$this->setExpectedException('LogicException', 'can be called only on INSERT statements.');
		$stmt = Statement::createDelete($this->connection, 'table');
		$stmt->insertId();
	}


	public function testInsertIdNotSupported(){
		$stmt = Statement::createInsert($this->connection, 'table', array('column', 'value'));
		$this->connection->getDriver()->setError('insert-id');
		$this->assertFalse($stmt->insertId());
	}


	public function testAffectedRows(){
		$stmt = Statement::createDelete($this->connection, 'table');
		$this->assertEquals(1, $stmt->affectedRows());
	}



	public function testAffectedRowsError(){
		$this->connection->getDriver()->setError('affected-rows');
		$stmt = Statement::createDelete($this->connection, 'table');
		$stmt->run();
		$r = new ReflectionProperty($stmt, 'affectedRows');
		$r->setAccessible(true);
		$this->assertFalse($r->getValue($stmt));

		$this->setExpectedException('Neevo\\DriverException', 'Affected rows are not supported by this driver.');
		$stmt->affectedRows();
	}


	public function testResetState(){
		$stmt = Statement::createDelete($this->connection, 'table');
		$stmt->affectedRows();

		$res = new ReflectionMethod($stmt, 'resetState');
		$res->setAccessible(true);
		$res->invoke($stmt);

		$aff = new ReflectionProperty('Neevo\Statement', 'affectedRows');
		$aff->setAccessible(true);
		$this->assertNull($aff->getValue($stmt));
	}


}
